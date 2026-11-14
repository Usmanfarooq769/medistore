<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DrugInteraction;
use App\Models\Medicine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Drug-safety check for a POS cart:
 *   1. allergy   – customer allergy text vs medicine name / generic (+ cross-reactive groups, e.g. penicillin)
 *   2. interaction – every pair of ingredients in the cart (and the customer's last 14 days of purchases)
 *   3. duplicate – two cart lines with the same active ingredient
 * This is decision support; the pharmacist stays responsible.
 */
class SafetyCheckService
{
    public function check(array $medicineIds, ?Customer $customer = null): array
    {
        $medicines = Medicine::whereIn('id', $medicineIds)->get(['id', 'name', 'strength', 'generic_name']);
        $cart = $medicines->map(fn ($m) => [
            'id' => $m->id, 'label' => trim($m->name . ' ' . ($m->strength ?? '')), 'ingredients' => $this->ingredients($m),
            'text' => mb_strtolower($m->name . ' ' . ($m->generic_name ?? '')),
        ]);

        $allergies = $this->allergyHits($cart, $customer);
        $interactions = $this->interactionHits($cart, $customer);
        $duplicates = $this->duplicates($cart);

        $level = 'none';
        if ($allergies || $duplicates || collect($interactions)->contains(fn ($i) => in_array($i['severity'], ['moderate', 'minor']))) $level = 'warning';
        if ($allergies || collect($interactions)->contains(fn ($i) => in_array($i['severity'], ['major', 'contraindicated']))) $level = 'danger';

        return ['level' => $level, 'allergies' => $allergies, 'interactions' => $interactions, 'duplicates' => $duplicates,
                'customer_allergies' => $customer?->allergies];
    }

    /** "Paracetamol + Caffeine" -> ['paracetamol','caffeine'] */
    public function ingredients(Medicine $m): array
    {
        $raw = mb_strtolower(trim((string) ($m->generic_name ?: $m->name)));
        return collect(preg_split('/\s*(?:\+|\/|,|;|&| and | with )\s*/u', $raw))
            ->map(fn ($s) => trim(preg_replace('/\b\d+(\.\d+)?\s*(mg|mcg|g|ml|iu|%)\b/u', '', $s)))
            ->filter(fn ($s) => mb_strlen($s) >= 3)->unique()->values()->all();
    }

    private function allergyHits(Collection $cart, ?Customer $customer): array
    {
        $text = mb_strtolower(trim((string) $customer?->allergies));
        if ($text === '') return [];
        $tokens = collect(preg_split('/[,;\n\/]+/u', $text))->map(fn ($t) => trim($t))->filter(fn ($t) => mb_strlen($t) >= 3);
        $groups = config('permissions.allergy_groups', []);

        $hits = [];
        foreach ($cart as $item) {
            foreach ($tokens as $token) {
                $match = str_contains($item['text'], $token);
                $via = null;
                if (! $match) {
                    foreach ($groups as $group => $keywords) {
                        if (! str_contains($token, $group)) continue;
                        foreach ($keywords as $kw) if (str_contains($item['text'], $kw)) { $match = true; $via = $group; break 2; }
                    }
                }
                if ($match) {
                    $hits[] = ['medicine_id' => $item['id'], 'medicine' => $item['label'], 'allergy' => $token,
                               'message' => $via ? "{$item['label']} is in the “{$via}” group – customer is allergic to {$token}." : "Customer is allergic to {$token} – matches {$item['label']}."];
                    break;
                }
            }
        }
        return $hits;
    }

    private function interactionHits(Collection $cart, ?Customer $customer): array
    {
        // ingredient => [labels]
        $pool = [];
        foreach ($cart as $item) foreach ($item['ingredients'] as $ing) $pool[$ing][] = ['label' => $item['label'], 'source' => 'cart'];

        if ($customer) {
            $recent = DB::table('sale_items as si')->join('sales as s', 's.id', '=', 'si.sale_id')->join('medicines as m', 'm.id', '=', 'si.medicine_id')
                ->where('s.customer_id', $customer->id)->where('s.sale_date', '>=', today()->subDays(14))
                ->whereNotIn('si.medicine_id', $cart->pluck('id'))->select('m.id', 'm.name', 'm.strength', 'm.generic_name')->distinct()->get();
            foreach ($recent as $r) {
                $m = new Medicine((array) $r);
                foreach ($this->ingredients($m) as $ing) $pool[$ing][] = ['label' => trim($r->name . ' ' . ($r->strength ?? '')) . ' (bought recently)', 'source' => 'recent'];
            }
        }
        if (count($pool) < 2) return [];

        $rules = DrugInteraction::where('is_active', true)->get();
        $out = [];
        $seen = [];
        foreach ($rules as $rule) {
            $aHits = $this->poolMatches($pool, $rule->ingredient_a);
            $bHits = $this->poolMatches($pool, $rule->ingredient_b);
            if (! $aHits || ! $bHits) continue;
            foreach ($aHits as $a) foreach ($bHits as $b) {
                if ($a['label'] === $b['label']) continue;
                if ($a['source'] === 'recent' && $b['source'] === 'recent') continue;
                $key = $rule->id . '|' . min($a['label'], $b['label']) . '|' . max($a['label'], $b['label']);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $out[] = ['severity' => $rule->severity, 'between' => [$a['label'], $b['label']], 'effect' => $rule->effect, 'management' => $rule->management];
            }
        }
        $order = ['contraindicated' => 0, 'major' => 1, 'moderate' => 2, 'minor' => 3];
        usort($out, fn ($x, $y) => $order[$x['severity']] <=> $order[$y['severity']]);
        return $out;
    }

    /** Ingredient in the pool whose name equals / contains the rule ingredient as a whole word. */
    private function poolMatches(array $pool, string $needle): array
    {
        $hits = [];
        foreach ($pool as $ingredient => $items) {
            if ($ingredient === $needle || preg_match('/\b' . preg_quote($needle, '/') . '\b/u', $ingredient)) $hits = array_merge($hits, $items);
        }
        return $hits;
    }

    private function duplicates(Collection $cart): array
    {
        $byIng = [];
        foreach ($cart as $item) foreach ($item['ingredients'] as $ing) $byIng[$ing][$item['id']] = $item['label'];
        $out = [];
        foreach ($byIng as $ing => $labels) {
            if (count($labels) > 1) $out[] = ['ingredient' => $ing, 'medicines' => array_values($labels), 'message' => 'Same active ingredient (' . $ing . ') appears in: ' . implode(' + ', $labels) . '.'];
        }
        return $out;
    }
}
