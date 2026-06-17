<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GeoStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlaceList extends Model
{
    use HasUuids;

    protected $fillable = [
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'icon',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            // Reuses the GeoStatus enum — same Active/Inactive vocab the
            // countries/cities/place_types use.
            'status' => GeoStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * Places curated into this list, ordered by the pivot's sort_order so
     * the landing page renders them in the order the admin chose.
     */
    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class, 'place_list_items')
            ->using(PlaceListItem::class)
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderBy('place_list_items.sort_order');
    }

    /** Only the lists currently surface-able on the landing page. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GeoStatus::Active->value);
    }
}
