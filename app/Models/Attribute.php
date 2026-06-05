<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttributePhotoRule;
use App\Enums\AttributeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasUuids;

    protected $fillable = [
        'group_id',
        'name_ar',
        'name_en',
        'icon',
        'question_ar',
        'question_en',
        'photo_rule',
        'type',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'photo_rule' => AttributePhotoRule::class,
            'options' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AttributeGroup::class, 'group_id');
    }

    /**
     * Concrete values of this attribute filled in on places.
     */
    public function placeValues(): HasMany
    {
        return $this->hasMany(PlaceAttribute::class);
    }
}
