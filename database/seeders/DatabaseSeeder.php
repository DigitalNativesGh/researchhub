<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: a demo researcher (demo@researchhub.com / password) and administrator
     * (admin@researchhub.com / password) with one sample project at every status. See DemoSeeder.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
