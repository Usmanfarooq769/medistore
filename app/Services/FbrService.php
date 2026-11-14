<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * FBR POS invoice integration (Pakistan).
 *
 * Settings (Settings -> FBR): fbr_enabled, fbr_pos_id, fbr_ntn, fbr_strn, fbr_api_url, fbr_api_token.
 *  - disabled            -> sale is untouched (fbr_status = not_required)
 *  - enabled, no API url -> a local FBR-style number is generated (status "local") so the receipt/QR is ready
 *  - enabled + API url   -> invoice is posted to FBR; their InvoiceNumber is stored (status "synced");
 *                           if FBR is unreachable the sale is NOT blocked: status "pending" and `php artisan store:fbr-sync` retries.
 *
 * IMPORTANT: use the endpoint, POS ID and token issued to your business by FBR/PRAL and test in their sandbox
 * before going live – field names below follow the published POS-integration JSON.
 */
class FbrService
{
    public function enabled(): bool
    {
        return setting('fbr_enabled', '0') === '1';
    }

    public function issue(Sale $sale): Sale
    {
        if (! $this->enabled()) return $sale;
        $sale->loadMissing('items');

        $url = trim((string) setting('fbr_api_url', ''));
        if ($url === '') {
            $seq = DB::table('sales')->whereDate('sale_date', $sale->sale_date)->where('id', '<=', $sale->id)->count();
            $sale->update([
                'fbr_invoice_no' => trim((string) setting('fbr_pos_id', 'POS')) . '-' . $sale->sale_date->format('Ymd') . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                'fbr_status' => 'local', 'fbr_error' => null, 'fbr_synced_at' => now(),
            ]);
            return $sale;
        }

        try {
            $response = Http::withToken((string) setting('fbr_api_token', ''))->acceptJson()->timeout(8)->post($url, $this->payload($sale));
            $number = $response->json('InvoiceNumber') ?? $response->json('invoiceNumber');
            if ($response->successful() && $number) {
                $sale->update(['fbr_invoice_no' => $number, 'fbr_status' => 'synced', 'fbr_error' => null, 'fbr_synced_at' => now()]);
            } else {
                $sale->update(['fbr_status' => 'pending', 'fbr_error' => mb_substr('FBR replied ' . $response->status() . ': ' . ($response->json('Response') ?? $response->body()), 0, 250)]);
            }
        } catch (\Throwable $e) {
            $sale->update(['fbr_status' => 'pending', 'fbr_error' => mb_substr('FBR not reachable: ' . $e->getMessage(), 0, 250)]);
        }
        return $sale;
    }

    public function payload(Sale $sale): array
    {
        $sale->loadMissing('items');
        $subtotal = max(0.01, (float) $sale->subtotal);
        $items = [];
        $qty = 0;
        foreach ($sale->items as $it) {
            $net = (float) $it->total;
            $share = $net / $subtotal;
            $lineDiscount = round((float) $sale->discount * $share, 2);
            $lineTax = round((float) $sale->tax * $share, 2);
            $qty += (int) $it->quantity;
            $items[] = [
                'ItemCode' => (string) $it->medicine_id, 'ItemName' => $it->medicine_name, 'Quantity' => (int) $it->quantity,
                'PCTCode' => '3004.9099', 'TaxRate' => (float) $sale->tax_percent, 'SaleValue' => round($net - $lineDiscount, 2),
                'TotalAmount' => round($net - $lineDiscount + $lineTax, 2), 'TaxCharged' => $lineTax, 'Discount' => $lineDiscount,
                'FurtherTax' => 0, 'InvoiceType' => 1, 'RefUSIN' => null,
            ];
        }
        return [
            'InvoiceNumber' => '', 'POSID' => (int) setting('fbr_pos_id', 0), 'USIN' => $sale->invoice_no,
            'DateTime' => $sale->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            'BuyerName' => $sale->customer_name, 'BuyerPhoneNumber' => $sale->customer_phone,
            'TotalBillAmount' => (float) $sale->total, 'TotalQuantity' => $qty,
            'TotalSaleValue' => round((float) $sale->subtotal - (float) $sale->discount, 2), 'TotalTaxCharged' => (float) $sale->tax,
            'Discount' => (float) $sale->discount, 'FurtherTax' => 0,
            'PaymentMode' => in_array($sale->payment_method, ['card', 'online'], true) ? 2 : 1, 'RefUSIN' => null, 'InvoiceType' => 1,
            'Items' => $items,
        ];
    }
}
