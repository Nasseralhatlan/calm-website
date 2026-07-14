<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ReviewStatus;
use App\Models\Place;
use App\Models\PlaceReview;
use Illuminate\Database\Seeder;

/**
 * Imports reviews for unregistered past guests WITHOUT creating any user
 * rows: the review row carries the display name itself (reviewer_name),
 * guest_user_id and booking_id stay null. That null pair is also the purge
 * marker — `php artisan reviews:purge-imported` removes exactly these rows
 * and never touches organic (booking-linked) reviews.
 *
 * Usage: fill REVIEWS below, then `php artisan db:seed --class=ImportedReviewsSeeder`.
 * Safe to re-run: one review per reviewer-name per place, updated in place.
 */
class ImportedReviewsSeeder extends Seeder
{
    /**
     * Overridable in tests; when empty the REVIEWS constant is used.
     *
     * @var list<array{place: string, reviewer: string, rate: int, comment: ?string, days_ago?: int}>
     */
    public static array $reviews = [];

    /**
     * `place` — the place's UUID, or (part of) its title in either language.
     * `reviewer` — the guest's display name (first word shows in the app).
     * `rate` — 1..5. `comment` — the review text (or null).
     * `days_ago` — how old the review should look (created_at back-dating).
     *
     * @var list<array{place: string, reviewer: string, rate: int, comment: ?string, days_ago?: int}>
     */
    private const REVIEWS = [
        // ['place' => 'شاليه الوسام', 'reviewer' => 'محمد العتيبي', 'rate' => 5, 'comment' => 'مكان نظيف وهادئ والتعامل راقي.', 'days_ago' => 60],
    ];

    public function run(): void
    {
        $reviews = static::$reviews !== [] ? static::$reviews : self::REVIEWS;

        if ($reviews === []) {
            $this->command?->warn('ImportedReviewsSeeder: REVIEWS list is empty — nothing to import.');

            return;
        }

        $imported = 0;

        foreach ($reviews as $entry) {
            $place = $this->findPlace((string) $entry['place']);

            if ($place === null) {
                $this->command?->warn("ImportedReviewsSeeder: place not found for '{$entry['place']}' — skipped.");

                continue;
            }

            PlaceReview::query()->updateOrCreate(
                [
                    'place_id' => $place->id,
                    'reviewer_name' => (string) $entry['reviewer'],
                    'guest_user_id' => null,
                ],
                [
                    'booking_id' => null,
                    'rate' => (int) $entry['rate'],
                    'comment' => $entry['comment'] ?? null,
                    'status' => ReviewStatus::Published->value,
                    'created_at' => now()->subDays((int) ($entry['days_ago'] ?? 0)),
                ],
            );

            $imported++;
        }

        $this->command?->info("ImportedReviewsSeeder: imported {$imported} review(s).");
    }

    private function findPlace(string $key): ?Place
    {
        return Place::query()
            ->where('id', $key)
            ->orWhere('title', 'like', "%{$key}%")
            ->orWhere('title_ar', 'like', "%{$key}%")
            ->orWhere('title_en', 'like', "%{$key}%")
            ->first();
    }
}
