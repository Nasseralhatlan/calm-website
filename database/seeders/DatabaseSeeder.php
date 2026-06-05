<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /*
     * NOTE: deliberately NOT using WithoutModelEvents — every model uses the
     * HasUuids trait, which generates its UUID inside the `creating` model
     * event. Suppressing events would leave new seeded rows with no primary
     * key and crash the insert.
     */

    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            CitySeeder::class,
            CityAreaSeeder::class,
            AdminSeeder::class,
            PlaceTypeSeeder::class,
            AttributeSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
