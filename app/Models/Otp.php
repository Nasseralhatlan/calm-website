<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OtpType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Otp extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'otp',
        'type',
        'attempts',
        'used',
        'expires_at',
    ];

    protected $hidden = [
        'otp',
    ];

    protected function casts(): array
    {
        return [
            'type' => OtpType::class,
            'used' => 'boolean',
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('used', false)->where('expires_at', '>', now());
    }

    public function scopeForType(Builder $query, OtpType $type): Builder
    {
        return $query->where('type', $type->value);
    }
}
