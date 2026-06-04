<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeGroup extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
    ];

    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class, 'group_id');
    }
}
