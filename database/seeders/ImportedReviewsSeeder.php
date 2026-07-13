<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ReviewStatus;
use App\Models\Place;
use App\Models\PlaceReview;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Imports REAL off-platform reviews (WhatsApp-era guests who never registered).
 * Each reviewer becomes a mock user so the review renders like any other in
 * the app (first name shown, placeholder avatar). Mock users carry a
 * `SEED-REV-###` phone — a shape no real registration can ever claim (real
 * phones are always 5########), which makes the whole batch identifiable and
 * fully removable later via `php artisan reviews:purge-imported`.
 *
 * Usage: fill REVIEWS below, then `php artisan db:seed --class=ImportedReviewsSeeder`.
 * Safe to re-run: one review per reviewer per place, updated in place.
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
     * `reviewer` — the real guest's display name (first word shows in the app).
     * `rate` — 1..5. `comment` — the real review text (or null).
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

            $reviewer = $this->reviewerUser((string) $entry['reviewer']);

            PlaceReview::query()->updateOrCreate(
                ['place_id' => $place->id, 'guest_user_id' => $reviewer->id],
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

    /**
     * One mock user per reviewer name, reused across runs. Phones are minted
     * sequentially in the reserved SEED-REV block.
     */
    private function reviewerUser(string $name): User
    {
        $existing = User::query()
            ->where('phone', 'like', 'SEED-REV-%')
            ->where('name', $name)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $next = User::query()->where('phone', 'like', 'SEED-REV-%')->count() + 1;

        return User::query()->create([
            'name' => $name,
            'phone' => sprintf('SEED-REV-%03d', $next),
            'locale' => 'ar',
        ]);
    }
}
