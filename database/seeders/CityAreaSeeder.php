<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\CityArea;
use Illuminate\Database\Seeder;

class CityAreaSeeder extends Seeder
{
    /**
     * Areas per city. Riyadh has the historical full list; the others get a
     * sensible "North / East / South / West" set so every city the host can
     * pick lands them on a non-empty area picker.
     *
     * @var array<string, list<array{name_en: string, name_ar: string}>>
     */
    private const AREAS_BY_CITY = [
        'Riyadh' => [
            ['name_en' => 'North',         'name_ar' => 'شمال'],
            ['name_en' => 'East',          'name_ar' => 'شرق'],
            ['name_en' => 'West',          'name_ar' => 'غرب'],
            ['name_en' => 'South',         'name_ar' => 'جنوب'],
            ['name_en' => 'Al Ammariyah',  'name_ar' => 'العمارية'],
            ['name_en' => 'Diriyah',       'name_ar' => 'الدرعية'],
        ],
        'Jeddah' => [
            ['name_en' => 'North Corniche', 'name_ar' => 'الكورنيش الشمالي'],
            ['name_en' => 'Al Hamra',       'name_ar' => 'الحمراء'],
            ['name_en' => 'Al Salamah',     'name_ar' => 'السلامة'],
            ['name_en' => 'Obhur',          'name_ar' => 'أبحر'],
        ],
        'Makkah' => [
            ['name_en' => 'Al Aziziyah',   'name_ar' => 'العزيزية'],
            ['name_en' => 'Al Naseem',     'name_ar' => 'النسيم'],
            ['name_en' => 'Al Awali',      'name_ar' => 'العوالي'],
        ],
        'Madinah' => [
            ['name_en' => 'Al Haram',      'name_ar' => 'الحرم'],
            ['name_en' => 'Quba',          'name_ar' => 'قباء'],
            ['name_en' => 'Al Aqiq',       'name_ar' => 'العقيق'],
        ],
        'Dammam' => [
            ['name_en' => 'Al Shatea',     'name_ar' => 'الشاطئ'],
            ['name_en' => 'Al Faisaliyah', 'name_ar' => 'الفيصلية'],
            ['name_en' => 'Al Rakah',      'name_ar' => 'الراكة'],
        ],
        'Khobar' => [
            ['name_en' => 'Corniche',      'name_ar' => 'الكورنيش'],
            ['name_en' => 'Al Aqrabiya',   'name_ar' => 'العقربية'],
            ['name_en' => 'Al Thuqbah',    'name_ar' => 'الثقبة'],
        ],
        'Abha' => [
            ['name_en' => 'Al Soudah',     'name_ar' => 'السودة'],
            ['name_en' => 'Al Manhal',     'name_ar' => 'المنهل'],
            ['name_en' => 'Al Sad',        'name_ar' => 'السد'],
        ],
        'Taif' => [
            ['name_en' => 'Al Shafa',      'name_ar' => 'الشفا'],
            ['name_en' => 'Al Hada',       'name_ar' => 'الهدا'],
            ['name_en' => 'Al Wesam',      'name_ar' => 'الوسام'],
        ],
    ];

    public function run(): void
    {
        foreach (self::AREAS_BY_CITY as $cityName => $areas) {
            $city = City::where('name_en', $cityName)->first();
            if (! $city) {
                continue;
            }

            foreach ($areas as $area) {
                CityArea::updateOrCreate(
                    ['city_id' => $city->id, 'name_en' => $area['name_en']],
                    ['name_ar' => $area['name_ar']],
                );
            }
        }
    }
}
