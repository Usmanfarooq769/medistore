<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Runs Stage 4, Stage 5 and Stage 6 reference seeders in dependency order. */
class StageFourFiveSixSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StageFourReferenceSeeder::class,
            StageFiveReferenceSeeder::class,
            StageSixReferenceSeeder::class,
        ]);
    }
}
