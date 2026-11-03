<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Safe, idempotent Stage 5 reference data. Does not create stock, sales, claims or user accounts. */
class StageFiveReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $required = ['branches','warehouse_locations','healthcare_facilities','customer_campaigns'];
        foreach ($required as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                throw new \RuntimeException("Stage 5 table [{$table}] is missing. Run php artisan migrate first.");
            }
        }

        DB::transaction(function (): void {
            $branches = DB::table('branches')->where('is_active', true)->get();
            foreach ($branches as $branch) {
                $locations = [
                    ['code'=>'WH-MAIN','name'=>'Main Warehouse','location_type'=>'zone','parent_id'=>null],
                    ['code'=>'RACK-A','name'=>'Rack A','location_type'=>'rack','parent_code'=>'WH-MAIN'],
                    ['code'=>'RACK-B','name'=>'Rack B','location_type'=>'rack','parent_code'=>'WH-MAIN'],
                    ['code'=>'COLD-01','name'=>'Cold Storage','location_type'=>'zone','parent_id'=>null],
                ];
                foreach ($locations as $location) {
                    $parentId = $location['parent_id'] ?? null;
                    if (!empty($location['parent_code'])) {
                        $parentId = DB::table('warehouse_locations')->where('branch_id',$branch->id)->where('code',$location['parent_code'])->value('id');
                    }
                    $exists=DB::table('warehouse_locations')->where('branch_id',$branch->id)->where('code',$location['code'])->exists();
                    if (!$exists) DB::table('warehouse_locations')->insert([
                        'branch_id'=>$branch->id,'code'=>$location['code'],'name'=>$location['name'],'location_type'=>$location['location_type'],
                        'parent_id'=>$parentId,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()
                    ]);
                }
            }

            $facilities = [
                ['registration_no'=>'DEMO-HOSP-5001','name'=>'Demo General Hospital','facility_type'=>'hospital','contact_person'=>'Demo Contact','phone'=>'03000005001','email'=>'hospital@example.test','address'=>'Demo address - replace before use'],
                ['registration_no'=>'DEMO-CLIN-5001','name'=>'Demo Family Clinic','facility_type'=>'clinic','contact_person'=>'Demo Contact','phone'=>'03000005002','email'=>'clinic@example.test','address'=>'Demo address - replace before use'],
                ['registration_no'=>'DEMO-LAB-5001','name'=>'Demo Diagnostic Laboratory','facility_type'=>'laboratory','contact_person'=>'Demo Contact','phone'=>'03000005003','email'=>'lab@example.test','address'=>'Demo address - replace before use'],
            ];
            foreach ($facilities as $facility) {
                if (!DB::table('healthcare_facilities')->where('registration_no',$facility['registration_no'])->exists()) {
                    DB::table('healthcare_facilities')->insert($facility + ['branch_id'=>null,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
                }
            }

            $campaigns = [
                ['name'=>'Demo Refill Reminder Template','campaign_type'=>'refill_reminder','message'=>'Hello {{customer_name}}, this is a reminder to check whether you need a medicine refill. Please contact your pharmacist.'],
                ['name'=>'Demo Loyalty Appreciation Template','campaign_type'=>'loyalty','message'=>'Thank you for choosing our pharmacy, {{customer_name}}. Your loyalty is appreciated.'],
            ];
            foreach ($campaigns as $campaign) {
                if (!DB::table('customer_campaigns')->where('name',$campaign['name'])->exists()) {
                    DB::table('customer_campaigns')->insert($campaign + ['status'=>'draft','starts_at'=>null,'ends_at'=>null,'created_by'=>null,'created_at'=>now(),'updated_at'=>now()]);
                }
            }
        });

        $this->command?->info('Stage 5 reference data seeded successfully.');
        $this->command?->comment('Demo facilities and campaigns are reference records only. Campaigns remain draft; no messages are sent.');
        $this->command?->comment('No users, medicines, stock, purchase orders, sales, insurance claims, or financial entries were created.');
    }
}
