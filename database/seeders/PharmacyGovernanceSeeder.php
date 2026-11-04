<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PharmacyGovernanceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductRoadmapSeeder::class);
    }
}
