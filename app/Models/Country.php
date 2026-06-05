<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GeoStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasUuids;

    protected $fillable = [
        'country_code',
        'dial_code',
        'name_ar',
        'name_en',
        'avatar',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => GeoStatus::class,
        ];
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Only the geo entries we currently surface in pickers. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GeoStatus::Active->value);
    }
}
