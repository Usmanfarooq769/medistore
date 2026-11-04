<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityEventLogger
{
    public function record(string $event, Request $request, ?int $userId = null, ?string $email = null, array $metadata = []): void
    {
        try {
            DB::table('security_events')->insert([
                'user_id'=>$userId,'event_type'=>$event,'email'=>$email,'ip_address'=>$request->ip(),
                'user_agent'=>substr((string)$request->userAgent(),0,2000),'metadata'=>json_encode($metadata),'created_at'=>now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
