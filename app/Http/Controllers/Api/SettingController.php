<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json($this->safe(Setting::allCached()));
    }

    /** never expose secrets to the browser */
    private function safe(array $settings): array
    {
        $settings['fbr_api_token_set'] = ! empty($settings['fbr_api_token']) ? '1' : '0';
        unset($settings['fbr_api_token']);
        return $settings;
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'store_name'           => 'required|string|max:120',
            'store_address'        => 'nullable|string|max:255',
            'store_phone'          => 'nullable|string|max:60',
            'license_no'           => 'nullable|string|max:60',
            'currency'             => 'required|string|max:10',
            'default_tax'          => 'required|numeric|min:0|max:100',
            'max_discount_percent' => 'required|numeric|min:0|max:100',
            'expiry_alert_days'    => 'required|integer|min:1|max:365',
            'return_days'          => 'required|integer|min:0|max:365',
            'cashier_price_edit'   => 'required|in:0,1',
            'receipt_footer'       => 'nullable|string|max:255',
            'daily_report_email'   => 'nullable|string|max:1000',
            'loyalty_earn_per_100' => 'required|integer|min:0|max:1000',
            'loyalty_point_value'  => 'required|numeric|min:0.01|max:10000',
            // receipt / compliance
            'receipt_width'        => 'nullable|in:58,80,a4',
            'store_ntn'            => 'nullable|string|max:30',
            'store_strn'           => 'nullable|string|max:30',
            'safety_check_enabled' => 'nullable|in:0,1',
            // FBR POS integration
            'fbr_enabled'          => 'nullable|in:0,1',
            'fbr_pos_id'           => 'nullable|string|max:30',
            'fbr_api_url'          => 'nullable|url|max:255',
            'fbr_api_token'        => 'nullable|string|max:500',
        ]);
        // an empty token field means "keep the saved one" (the token is never sent back to the browser)
        if (($data['fbr_api_token'] ?? '') === '') unset($data['fbr_api_token']);
        foreach (['receipt_width' => '80', 'safety_check_enabled' => '1', 'fbr_enabled' => '0'] as $k => $default) $data[$k] = $data[$k] ?? $default;
        foreach (['store_ntn', 'store_strn', 'fbr_pos_id', 'fbr_api_url'] as $k) $data[$k] = $data[$k] ?? '';

        if (! empty($data['daily_report_email'])) {
            foreach (preg_split('/[,;]+/', $data['daily_report_email']) as $email) {
                if (! filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                    return response()->json(['message' => 'Enter valid email address(es), separated by commas.'], 422);
                }
            }
        }

        Setting::put($data);

        return response()->json(['message' => 'Settings saved.', 'data' => $this->safe(Setting::allCached())]);
    }
}
