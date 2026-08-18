<?php

namespace Database\Seeders;

use App\Services\AgencySeeder;
use App\Services\Database;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        AgencySeeder::run(app(Database::class));
    }
}
