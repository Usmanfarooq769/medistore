<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Runs the Stage 4 and Stage 5 reference seeders in dependency order. */
class StageFourFiveSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StageFourReferenceSeeder::class,
            StageFiveReferenceSeeder::class,
        ]);
    }
}
