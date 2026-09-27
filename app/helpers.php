<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /** Read a store setting (cached). */
    function setting(string $key, $default = null)
    {
        $all = Setting::allCached();

        return array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '' ? $all[$key] : $default;
    }
}

if (! function_exists('money')) {
    function money($amount): string
    {
        return setting('currency', 'Rs.') . ' ' . number_format((float) $amount, 2);
    }
}

if (! function_exists('payment_status')) {
    function payment_status(float $total, float $paid): string
    {
        if ($paid <= 0 && $total > 0) {
            return 'unpaid';
        }

        return $paid + 0.001 >= $total ? 'paid' : 'partial';
    }
}
