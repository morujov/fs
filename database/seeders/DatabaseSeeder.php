<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Справочники — всегда, включая прод.
        $this->call([
            ProvinceSeeder::class,
            OperatorSeeder::class,
            BlocklistSeeder::class,
            SettingSeeder::class,
        ]);

        // Тестовые данные — только вне прода.
        if (app()->environment('production')) {
            $this->command->warn('Production: демо-данные пропущены.');

            return;
        }

        $this->call(DemoListingSeeder::class);
    }
}
