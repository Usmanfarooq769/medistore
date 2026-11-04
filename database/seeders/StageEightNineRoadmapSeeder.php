<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StageEightNineRoadmapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(StageEightNineReferenceSeeder::class);
    }
}
