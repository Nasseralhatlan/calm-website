<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GeoStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaceType extends Model
{
    use HasUuids;

    protected $fillable = [
        'name_ar',
        'name_en',
        'icon',
        'status',
    ];

    protected function casts(): array
    {
        return [
            // Reuses the GeoStatus enum — same shape, same Active/Inactive
            // semantics across all "is this surface-able yet?" toggles.
            'status' => GeoStatus::class,
        ];
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    /** Only the place types we currently surface in the host wizard. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GeoStatus::Active->value);
    }
}
