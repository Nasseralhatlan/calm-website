<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FaqAudience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasUuids;

    protected $fillable = [
        'audience',
        'question_ar',
        'question_en',
        'answer_ar',
        'answer_en',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'audience' => FaqAudience::class,
            'sort_order' => 'integer',
        ];
    }

    /** Admin-controlled display order (lower first, oldest as tiebreaker). */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->oldest('created_at');
    }

    /** Entries written for one audience (guest or host). */
    public function scopeForAudience(Builder $query, FaqAudience $audience): Builder
    {
        return $query->where('audience', $audience->value);
    }
}
