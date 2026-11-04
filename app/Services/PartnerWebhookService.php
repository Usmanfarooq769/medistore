<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class PartnerWebhookService
{
    public function queue(string $event, array $payload): int
    {
        $count = 0;
        $endpoints = DB::table('partner_webhook_endpoints')->where('status', 'active')->get();
        foreach ($endpoints as $endpoint) {
            $events = json_decode($endpoint->events, true) ?: [];
            if (! in_array('*', $events, true) && ! in_array($event, $events, true)) continue;
            DB::table('partner_webhook_deliveries')->insert([
                'endpoint_id' => $endpoint->id, 'event_name' => $event, 'payload' => json_encode($payload),
                'status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $count++;
        }
        return $count;
    }

    public function deliverPending(int $limit = 50): array
    {
        $result = ['delivered' => 0, 'failed' => 0];
        $deliveries = DB::table('partner_webhook_deliveries')->where('status', 'pending')->where('next_attempt_at', '<=', now())->orderBy('id')->limit($limit)->get();
        foreach ($deliveries as $delivery) {
            $endpoint = DB::table('partner_webhook_endpoints')->where('id', $delivery->endpoint_id)->where('status', 'active')->first();
            if (! $endpoint) {
                DB::table('partner_webhook_deliveries')->where('id', $delivery->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                continue;
            }
            $payload = json_decode($delivery->payload, true) ?: [];
            $body = json_encode(['event' => $delivery->event_name, 'delivery_id' => $delivery->id, 'data' => $payload], JSON_UNESCAPED_SLASHES);
            try {
                $secret = Crypt::decryptString($endpoint->signing_secret);
                $response = Http::timeout(10)->withOptions(['allow_redirects' => false])->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-MediStore-Signature' => hash_hmac('sha256', $body, $secret),
                    'X-MediStore-Event' => $delivery->event_name,
                ])->withBody($body, 'application/json')->send('POST', $endpoint->url);
                $ok = $response->successful();
                $attempts = $delivery->attempts + 1;
                DB::table('partner_webhook_deliveries')->where('id', $delivery->id)->update([
                    'status' => $ok ? 'delivered' : ($attempts >= 5 ? 'failed' : 'pending'),
                    'attempts' => $attempts, 'response_code' => $response->status(),
                    'response_excerpt' => mb_substr($response->body(), 0, 1000),
                    'next_attempt_at' => $ok || $attempts >= 5 ? null : now()->addMinutes(min(60, 2 ** $attempts)),
                    'delivered_at' => $ok ? now() : null, 'updated_at' => now(),
                ]);
                $result[$ok ? 'delivered' : 'failed']++;
            } catch (Throwable $e) {
                $attempts = $delivery->attempts + 1;
                DB::table('partner_webhook_deliveries')->where('id', $delivery->id)->update([
                    'status' => $attempts >= 5 ? 'failed' : 'pending', 'attempts' => $attempts,
                    'response_excerpt' => mb_substr($e->getMessage(), 0, 1000),
                    'next_attempt_at' => $attempts >= 5 ? null : now()->addMinutes(min(60, 2 ** $attempts)), 'updated_at' => now(),
                ]);
                $result['failed']++;
            }
        }
        return $result;
    }
}
