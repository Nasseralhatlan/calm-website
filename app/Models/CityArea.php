<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CityArea extends Model
{
    use HasUuids;

    protected $table = 'city_areas';

    protected $fillable = [
        'city_id',
        'name_ar',
        'name_en',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }
}
