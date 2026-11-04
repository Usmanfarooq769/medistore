<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\ProductRoadmapMilestone;

class StageEightNineReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductRoadmapSeeder::class);

        $plans = [
            ['code'=>'starter','name'=>'Starter','description'=>'For a single pharmacy branch.','monthly_price'=>0,'annual_price'=>0,'branch_limit'=>1,'user_limit'=>5,'features'=>json_encode(['inventory','pos','basic_reports'])],
            ['code'=>'professional','name'=>'Professional','description'=>'For growing pharmacy operations.','monthly_price'=>0,'annual_price'=>0,'branch_limit'=>5,'user_limit'=>30,'features'=>json_encode(['inventory','pos','analytics','customer_portal','partner_api'])],
            ['code'=>'enterprise','name'=>'Enterprise','description'=>'For pharmacy chains and healthcare groups.','monthly_price'=>0,'annual_price'=>0,'branch_limit'=>100,'user_limit'=>500,'features'=>json_encode(['all_modules','multi_branch','integrations','advanced_reports'])],
        ];
        foreach ($plans as $plan) {
            DB::table('subscription_plans')->insertOrIgnore($plan + ['is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }

        ProductRoadmapMilestone::where('milestone_number', 8)->where('status', 'planned')->update(['status'=>'in_progress']);
    }
}
