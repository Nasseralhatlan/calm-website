<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;
use RuntimeException;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $saudi = Country::where('country_code', 'SA')->first();

        if (! $saudi) {
            throw new RuntimeException('Saudi Arabia must be seeded before cities. Run CountrySeeder first.');
        }

        City::updateOrCreate(
            ['country_id' => $saudi->id, 'name_en' => 'Riyadh'],
            ['name_ar' => 'الرياض'],
        );
    }
}
