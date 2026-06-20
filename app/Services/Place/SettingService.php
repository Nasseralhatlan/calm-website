<?php

declare(strict_types=1);

namespace App\Services\Place;

use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SettingService
{
    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        return Setting::query()->orderBy('key')->paginate($perPage ?? config('pagination.per_page'));
    }

    /**
     * Key → value map for a subset of settings. Used by the landing page to
     * render the support email + phone without N queries.
     *
     * @param  list<string>  $keys
     * @return array<string, string|null>
     */
    public function byKeys(array $keys): array
    {
        return Setting::query()
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * Settings the mobile app is allowed to read. The exposed keys are a
     * hardcoded whitelist on purpose — clients can't ask for arbitrary settings,
     * so admin-only values (e.g. commission_percentage) never leak. Every
     * whitelisted key is always present (null when unset). Expose another
     * setting to the app by adding its key here.
     *
     * @return array<string, string|null>
     */
    public function publicSettings(): array
    {
        $keys = ['support_phone', 'support_email'];

        $values = $this->byKeys($keys);

        return array_reduce(
            $keys,
            fn (array $carry, string $key): array => [...$carry, $key => $values[$key] ?? null],
            [],
        );
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
