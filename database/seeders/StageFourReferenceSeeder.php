<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Inserts safe, reusable Stage 4 reference/demo data.
 *
 * This seeder intentionally does NOT create sales, purchases, stock batches,
 * expiry claims, insurance claims, settlements, referrals, or journal entries.
 * It also does not create login accounts or change existing users.
 */
class StageFourReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureStageFourTablesExist();

        DB::transaction(function (): void {
            $branchId = DB::table('branches')->where('code', 'MAIN')->value('id')
                ?: DB::table('branches')->orderBy('id')->value('id');

            if (!$branchId) {
                $branchId = DB::table('branches')->insertGetId([
                    'name' => 'Main Branch',
                    'code' => 'MAIN',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->seedDoctorsAndClinics($branchId);
            $this->seedManufacturerSchemes($branchId);
            $this->seedInsuranceMasterData();
        });

        $this->command?->info('Stage 4 reference data seeded successfully.');
        $this->command?->comment('Demo manufacturer schemes are inactive by default. Review and activate only the schemes agreed with your suppliers.');
        $this->command?->comment('No sales, purchases, stock, claims, settlements, or user accounts were created.');
    }

    private function ensureStageFourTablesExist(): void
    {
        $required = [
            'branches', 'doctors', 'clinics', 'clinic_doctor',
            'manufacturer_schemes', 'insurance_providers',
            'insurance_plans', 'insurance_coverages',
        ];

        foreach ($required as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                throw new \RuntimeException(
                    "Stage 4 table [{$table}] was not found. Run php artisan migrate before running this seeder."
                );
            }
        }
    }

    private function seedDoctorsAndClinics(int $branchId): void
    {
        $doctors = [
            [
                'license_no' => 'DEMO-PMC-10001',
                'name' => 'Dr. Ahmed Raza',
                'specialty' => 'General Physician',
                'phone' => '03000001001',
                'email' => 'ahmed.raza@example.test',
                'address' => 'Demo address - update before use',
            ],
            [
                'license_no' => 'DEMO-PMC-10002',
                'name' => 'Dr. Sara Malik',
                'specialty' => 'Pediatrics',
                'phone' => '03000001002',
                'email' => 'sara.malik@example.test',
                'address' => 'Demo address - update before use',
            ],
            [
                'license_no' => 'DEMO-PMC-10003',
                'name' => 'Dr. Usman Tariq',
                'specialty' => 'Dermatology',
                'phone' => '03000001003',
                'email' => 'usman.tariq@example.test',
                'address' => 'Demo address - update before use',
            ],
        ];

        $doctorIds = [];
        foreach ($doctors as $data) {
            $now = now();
            DB::table('doctors')->updateOrInsert(
                ['license_no' => $data['license_no']],
                $data + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
            $doctorIds[] = (int) DB::table('doctors')->where('license_no', $data['license_no'])->value('id');
        }

        $clinics = [
            ['name' => 'MediCare Family Clinic - Demo', 'phone' => '03000002001', 'email' => 'medicare@example.test', 'address' => 'Demo clinic address'],
            ['name' => 'City Children Clinic - Demo', 'phone' => '03000002002', 'email' => 'children@example.test', 'address' => 'Demo clinic address'],
        ];

        $clinicIds = [];
        foreach ($clinics as $data) {
            $now = now();
            $existingId = DB::table('clinics')->where('name', $data['name'])->value('id');
            if ($existingId) {
                DB::table('clinics')->where('id', $existingId)->update($data + ['branch_id' => $branchId, 'is_active' => true, 'updated_at' => $now]);
                $clinicId = (int) $existingId;
            } else {
                $clinicId = (int) DB::table('clinics')->insertGetId($data + [
                    'branch_id' => $branchId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $clinicIds[] = $clinicId;
        }

        $links = [
            [$clinicIds[0], $doctorIds[0]],
            [$clinicIds[0], $doctorIds[2]],
            [$clinicIds[1], $doctorIds[1]],
        ];

        foreach ($links as [$clinicId, $doctorId]) {
            DB::table('clinic_doctor')->updateOrInsert(
                ['clinic_id' => $clinicId, 'doctor_id' => $doctorId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedManufacturerSchemes(int $branchId): void
    {
        $medicine = DB::table('medicines')->where('is_active', true)->orderBy('id')->first();
        if (!$medicine) {
            $this->command?->warn('No active medicines found. Manufacturer scheme examples were skipped.');
            return;
        }

        $manufacturerId = $medicine->manufacturer_id
            ?: DB::table('manufacturers')->orderBy('id')->value('id');
        $supplierId = DB::table('suppliers')->orderBy('id')->value('id');

        $schemes = [
            [
                'name' => 'DEMO - Buy 10 Get 2 Free',
                'scheme_type' => 'buy_x_get_y',
                'buy_quantity' => 10,
                'free_quantity' => 2,
                'discount_percent' => 0,
                'terms' => 'DEMO ONLY. Confirm the actual scheme with the manufacturer/distributor before activation.',
            ],
            [
                'name' => 'DEMO - Distributor 5 Percent Discount',
                'scheme_type' => 'discount',
                'buy_quantity' => 1,
                'free_quantity' => 0,
                'discount_percent' => 5,
                'terms' => 'DEMO ONLY. Confirm eligibility and discount settlement with the supplier before activation.',
            ],
        ];

        foreach ($schemes as $scheme) {
            $attributes = $scheme + [
                'manufacturer_id' => $manufacturerId,
                'supplier_id' => $supplierId,
                'medicine_id' => $medicine->id,
                'branch_id' => $branchId,
                'valid_from' => today()->toDateString(),
                'valid_to' => today()->addMonths(6)->toDateString(),
                'max_free_quantity' => null,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $existing = DB::table('manufacturer_schemes')->where('name', $scheme['name'])->first();
            if ($existing) {
                // Do not reactivate or overwrite a scheme a manager has edited.
                continue;
            }

            DB::table('manufacturer_schemes')->insert($attributes);
        }
    }

    private function seedInsuranceMasterData(): void
    {
        $providers = [
            [
                'code' => 'DEMO-TPA-01',
                'name' => 'Demo Health Insurance Provider',
                'phone' => '03000003001',
                'email' => 'support@example.test',
                'claims_email' => 'claims@example.test',
                'address' => 'Demo provider address - replace with actual details',
            ],
            [
                'code' => 'DEMO-PANEL-01',
                'name' => 'Demo Corporate Panel',
                'phone' => '03000003002',
                'email' => 'panel@example.test',
                'claims_email' => 'panel-claims@example.test',
                'address' => 'Demo panel address - replace with actual details',
            ],
        ];

        $planIds = [];
        foreach ($providers as $provider) {
            $now = now();
            DB::table('insurance_providers')->updateOrInsert(
                ['code' => $provider['code']],
                $provider + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );

            $providerId = (int) DB::table('insurance_providers')->where('code', $provider['code'])->value('id');
            $planName = 'Demo Standard Pharmacy Plan';
            DB::table('insurance_plans')->updateOrInsert(
                ['provider_id' => $providerId, 'name' => $planName],
                [
                    'plan_code' => 'DEMO-STANDARD',
                    'annual_limit' => 100000,
                    'default_coverage_percent' => 80,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $planIds[] = (int) DB::table('insurance_plans')
                ->where('provider_id', $providerId)->where('name', $planName)->value('id');
        }

        $medicineIds = DB::table('medicines')->where('is_active', true)->orderBy('id')->limit(5)->pluck('id');
        foreach ($planIds as $planId) {
            foreach ($medicineIds as $medicineId) {
                DB::table('insurance_coverages')->updateOrInsert(
                    ['plan_id' => $planId, 'medicine_id' => $medicineId],
                    [
                        'coverage_percent' => 80,
                        'max_amount' => 5000,
                        'is_covered' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
