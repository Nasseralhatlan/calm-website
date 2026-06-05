<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Baseline settings the admin panel exposes for editing. Values here are
     * sensible defaults — the admin tweaks them in production via the settings
     * screen. `updateOrCreate` keeps any admin-set value intact on re-seed.
     *
     * @var array<array{key: string, value: string}>
     */
    private const SETTINGS = [
        ['key' => 'vat_percentage',        'value' => '15'],          // KSA standard rate
        ['key' => 'commission_percentage', 'value' => '10'],          // Calm's cut per booking
        ['key' => 'support_email',         'value' => 'support@calmapp.co'],
        ['key' => 'support_phone',         'value' => '+966500000000'],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']],
            );
        }
    }
}
