<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SettingService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return Setting::query()->orderBy('key')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Setting
    {
        return Setting::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Setting $setting, array $data): Setting
    {
        $setting->update($data);

        return $setting->refresh();
    }

    public function delete(Setting $setting): void
    {
        $setting->delete();
    }
}
