<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaceType extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
        'icon',
    ];

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }
}
