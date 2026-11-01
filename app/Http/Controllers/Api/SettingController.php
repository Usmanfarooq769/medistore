<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(Setting::allCached());
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
        ]);

        if (! empty($data['daily_report_email'])) {
            foreach (preg_split('/[,;]+/', $data['daily_report_email']) as $email) {
                if (! filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                    return response()->json(['message' => 'Enter valid email address(es), separated by commas.'], 422);
                }
            }
        }

        Setting::put($data);

        return response()->json(['message' => 'Settings saved.', 'data' => Setting::allCached()]);
    }
}
