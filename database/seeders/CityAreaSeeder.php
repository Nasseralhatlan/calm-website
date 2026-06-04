<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\CityArea;
use Illuminate\Database\Seeder;
use RuntimeException;

class CityAreaSeeder extends Seeder
{
    public function run(): void
    {
        $riyadh = City::where('name_en', 'Riyadh')->first();

        if (! $riyadh) {
            throw new RuntimeException('Riyadh must be seeded before city areas. Run CitySeeder first.');
        }

        $areas = [
            ['name_ar' => 'شمال',     'name_en' => 'North'],
            ['name_ar' => 'شرق',      'name_en' => 'East'],
            ['name_ar' => 'غرب',      'name_en' => 'West'],
            ['name_ar' => 'جنوب',     'name_en' => 'South'],
            ['name_ar' => 'العمارية', 'name_en' => 'Al Ammariyah'],
            ['name_ar' => 'الدرعية',  'name_en' => 'Diriyah'],
        ];

        foreach ($areas as $area) {
            CityArea::updateOrCreate(
                ['city_id' => $riyadh->id, 'name_en' => $area['name_en']],
                ['name_ar' => $area['name_ar']],
            );
        }
    }
}
