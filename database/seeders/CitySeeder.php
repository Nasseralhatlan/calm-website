<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;
use RuntimeException;

class CitySeeder extends Seeder
{
    /**
     * Popular Saudi cities for the host wizard's city picker. The `avatar`
     * column stores an emoji we render as the visual icon on the card.
     */
    private const CITIES = [
        ['name_en' => 'Riyadh',   'name_ar' => 'الرياض',     'avatar' => '🌴'],
        ['name_en' => 'Jeddah',   'name_ar' => 'جدة',        'avatar' => '🌊'],
        ['name_en' => 'Makkah',   'name_ar' => 'مكة المكرمة', 'avatar' => '🕋'],
        ['name_en' => 'Madinah',  'name_ar' => 'المدينة المنورة', 'avatar' => '🕌'],
        ['name_en' => 'Dammam',   'name_ar' => 'الدمام',     'avatar' => '🏙️'],
        ['name_en' => 'Khobar',   'name_ar' => 'الخبر',      'avatar' => '🌅'],
        ['name_en' => 'Abha',     'name_ar' => 'أبها',        'avatar' => '⛰️'],
        ['name_en' => 'Taif',     'name_ar' => 'الطائف',     'avatar' => '🌹'],
    ];

    public function run(): void
    {
        $saudi = Country::where('country_code', 'SA')->first();

        if (! $saudi) {
            throw new RuntimeException('Saudi Arabia must be seeded before cities. Run CountrySeeder first.');
        }

        foreach (self::CITIES as $city) {
            City::updateOrCreate(
                ['country_id' => $saudi->id, 'name_en' => $city['name_en']],
                ['name_ar' => $city['name_ar'], 'avatar' => $city['avatar']],
            );
        }
    }
}
