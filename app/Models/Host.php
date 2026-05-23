<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Host extends Model
{
    protected $fillable = ['slug', 'phone', 'place_type', 'title', 'description', 'max_guests'];

    public function facilities(): HasMany
    {
        return $this->hasMany(HostFacility::class);
    }

    public function amenities(): HasMany
    {
        return $this->hasMany(HostAmenity::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(HostImage::class)->orderBy('sort');
    }
}
