<?php

declare(strict_types=1);

use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceReview;
use App\Models\PlaceType;
use App\Models\User;
use Database\Seeders\ImportedReviewsSeeder;

beforeEach(function (): void {
    $this->seed();
    ImportedReviewsSeeder::$reviews = [];
});

afterEach(function (): void {
    ImportedReviewsSeeder::$reviews = [];
});

function importTargetPlace(): Place
{
    $host = User::factory()->create(['phone' => '518300001']);

    return Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'شاليه الوسام',
        'title_ar' => 'شاليه الوسام',
        'description' => 'x',
        'price' => 900,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 6,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ]);
}

it('imports reviews as published, back-dated, accountless rows — no users created', function (): void {
    $place = importTargetPlace();
    $usersBefore = User::query()->count();

    ImportedReviewsSeeder::$reviews = [
        // Matched by title fragment; back-dated 60 days.
        ['place' => 'الوسام', 'reviewer' => 'محمد العتيبي', 'rate' => 5, 'comment' => 'مكان نظيف وهادئ.', 'days_ago' => 60],
        // Matched by UUID.
        ['place' => $place->id, 'reviewer' => 'سارة الدوسري', 'rate' => 4, 'comment' => 'تجربة جميلة.', 'days_ago' => 10],
    ];
    $this->seed(ImportedReviewsSeeder::class);

    $reviews = PlaceReview::query()->where('place_id', $place->id)->orderBy('rate')->get();
    expect($reviews)->toHaveCount(2)
        ->and($reviews->every(fn ($r) => $r->status->value === 'published'))->toBeTrue()
        ->and($reviews->every(fn ($r) => $r->guest_user_id === null && $r->booking_id === null))->toBeTrue()
        ->and($reviews->last()->created_at->lessThan(now()->subDays(59)))->toBeTrue()
        // The whole point: zero user rows minted.
        ->and(User::query()->count())->toBe($usersBefore);

    // The app renders them like any other review: first name + aggregates.
    $this->getJson('/api/places/'.$place->id)
        ->assertOk()
        ->assertJsonPath('data.rating.count', 2)
        ->assertJsonPath('data.rating.avg', 4.5)
        ->assertJsonPath('data.reviews_recent.0.reviewer_name', 'سارة')
        ->assertJsonPath('data.reviews_recent.1.reviewer_name', 'محمد');
});

it('is idempotent: re-running updates in place instead of duplicating', function (): void {
    $place = importTargetPlace();

    ImportedReviewsSeeder::$reviews = [
        ['place' => 'الوسام', 'reviewer' => 'محمد العتيبي', 'rate' => 5, 'comment' => 'أول نص.', 'days_ago' => 30],
    ];
    $this->seed(ImportedReviewsSeeder::class);

    ImportedReviewsSeeder::$reviews = [
        ['place' => 'الوسام', 'reviewer' => 'محمد العتيبي', 'rate' => 3, 'comment' => 'نص محدث.', 'days_ago' => 30],
    ];
    $this->seed(ImportedReviewsSeeder::class);

    $reviews = PlaceReview::query()->where('place_id', $place->id)->get();
    expect($reviews)->toHaveCount(1)
        ->and($reviews->first()->rate)->toBe(3)
        ->and($reviews->first()->comment)->toBe('نص محدث.');
});

it('skips unknown places without failing the batch', function (): void {
    $place = importTargetPlace();

    ImportedReviewsSeeder::$reviews = [
        ['place' => 'مكان غير موجود إطلاقًا', 'reviewer' => 'خالد', 'rate' => 5, 'comment' => null],
        ['place' => 'الوسام', 'reviewer' => 'نورة', 'rate' => 4, 'comment' => 'رائع.'],
    ];
    $this->seed(ImportedReviewsSeeder::class);

    expect(PlaceReview::query()->count())->toBe(1)
        ->and(PlaceReview::query()->first()->place_id)->toBe($place->id);
});
