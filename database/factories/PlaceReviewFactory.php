<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\PlaceReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaceReview>
 */
class PlaceReviewFactory extends Factory
{
    protected $model = PlaceReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rate' => fake()->numberBetween(3, 5),
            'comment' => fake()->sentence(),
            'status' => ReviewStatus::Published->value,
        ];
    }

    public function underReview(): static
    {
        return $this->state(['status' => ReviewStatus::UnderReview->value]);
    }

    public function blocked(): static
    {
        return $this->state(['status' => ReviewStatus::Blocked->value]);
    }
}
