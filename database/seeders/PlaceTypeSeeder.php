<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\GeoStatus;
use App\Models\PlaceType;
use Illuminate\Database\Seeder;

class PlaceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // Chalets are typically beach/lake recreational rentals in KSA.
            ['name_en' => 'Chalets',       'name_ar' => 'شاليهات',      'icon' => '🏖️'],
            // Istrahahat are urban rest houses, often featuring palm gardens.
            ['name_en' => 'Resthouses',    'name_ar' => 'استراحات',     'icon' => '🌴'],
            ['name_en' => 'Farms & Camps', 'name_ar' => 'مزارع ومخيمات', 'icon' => '🏕️'],
        ];

        foreach ($types as $type) {
            PlaceType::query()->updateOrCreate(
                ['name_en' => $type['name_en']],
                [
                    'name_ar' => $type['name_ar'],
                    'icon' => $type['icon'],
                    'status' => GeoStatus::Active->value,
                ],
            );
        }
    }
}
