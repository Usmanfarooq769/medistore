<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Stage 6 configuration defaults only. Does not create fake devices, contracts, orders or readings. */
class StageSixReferenceSeeder extends Seeder
{
    public function run(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('settings')) {
            throw new \RuntimeException('Settings table is missing. Run migrations first.');
        }

        $defaults = [
            'stage6_online_order_requires_staff_confirmation' => '1',
            'stage6_public_catalog_hide_prescription_medicines' => '1',
            'stage6_public_catalog_hide_controlled_medicines' => '1',
            'stage6_delivery_requires_linked_pos_sale' => '1',
            'stage6_cold_chain_excursions_require_acknowledgement' => '1',
        ];

        foreach ($defaults as $key => $value) {
            if (!DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $this->command?->info('Stage 6 default settings inserted. Existing settings were preserved.');
        $this->command?->comment('No sample customer orders, deliveries, temperature readings, supplier contracts or financial records were created.');
    }
}
