<?php

declare(strict_types=1);

use App\Enums\GeoStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Enums\ReviewStatus;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceList;
use App\Models\PlaceReview;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
    $this->host = User::factory()->create(['phone' => '516200001']);
});

function homePagePlace(User $host, array $attrs = []): Place
{
    return Place::query()->create(array_merge([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title_ar' => 'شاليه الصفحة الرئيسية',
        'description' => 'x',
        'price' => 800,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 6,
        'status' => PlaceStatus::Active->value,
        'review_status' => PlaceReviewStatus::Approved->value,
    ], $attrs));
}

function homePageList(string $name, Place $place, string $status = 'active'): PlaceList
{
    $list = PlaceList::query()->create([
        'name_ar' => $name,
        'name_en' => $name,
        'status' => $status,
        'sort_order' => 1,
    ]);
    $list->places()->attach($place->id, ['sort_order' => 1]);

    return $list;
}

it('renders the browse home: quick types, curated lists, and a sign-in button for guests', function (): void {
    $place = homePagePlace($this->host);
    homePageList('مختارات كالم', $place);

    $this->get('/')
        ->assertOk()
        // Quick place-type boxes (seeded types).
        ->assertSee('شاليهات')
        // Curated list + its place card, server-rendered.
        ->assertSee('مختارات كالم')
        ->assertSee('شاليه الصفحة الرئيسية')
        ->assertSee(route('places.show', $place))
        // Guest chrome: sign-in button, no floating tab bar.
        ->assertSee('/login')
        ->assertDontSee(route('user.my-bookings'));
});

it('hides inactive lists and lists whose places are not visible', function (): void {
    $visible = homePagePlace($this->host, ['title_ar' => 'مكان ظاهر']);
    homePageList('قائمة موقوفة', $visible, GeoStatus::Inactive->value);

    $hidden = homePagePlace($this->host, [
        'title_ar' => 'مكان غير معتمد',
        'review_status' => PlaceReviewStatus::PendingReview->value,
        'status' => PlaceStatus::Inactive->value,
    ]);
    homePageList('قائمة بلا أماكن ظاهرة', $hidden);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('قائمة موقوفة')
        ->assertDontSee('قائمة بلا أماكن ظاهرة')
        ->assertDontSee('مكان غير معتمد');
});

it('shows the floating tab bar for signed-in users', function (): void {
    $this->actingAs($this->host, 'api')
        ->get('/')
        ->assertOk()
        ->assertSee(route('user.my-bookings'))
        ->assertSee(route('user.favorites'))
        ->assertSee(route('profile'));
});

it('renders the favorites page for signed-in users and blocks guests', function (): void {
    $this->get('/favorites')->assertRedirect();

    $this->actingAs($this->host, 'api')
        ->get('/favorites')
        ->assertOk()
        ->assertSee('/api/favorites');
});

it('shows the booking CTA on a live place page, linking to the funnel', function (): void {
    $place = homePagePlace($this->host);

    $this->get(route('places.show', $place))
        ->assertOk()
        ->assertSee(route('book.show', $place));
});

it('shows the rating chip and recent published reviews on the place page', function (): void {
    $place = homePagePlace($this->host);
    PlaceReview::query()->create([
        'place_id' => $place->id,
        'reviewer_name' => 'سارة العتيبي',
        'rate' => 5,
        'comment' => 'مكان هادئ ونظيف والتعامل راقي.',
        'status' => ReviewStatus::Published->value,
    ]);
    PlaceReview::query()->create([
        'place_id' => $place->id,
        'reviewer_name' => 'مخفي',
        'rate' => 1,
        'comment' => 'تقييم قيد المراجعة يجب ألا يظهر.',
        'status' => ReviewStatus::UnderReview->value,
    ]);

    $this->get(route('places.show', $place))
        ->assertOk()
        ->assertSee('التقييمات')
        // First name only, like the app.
        ->assertSee('سارة')
        ->assertDontSee('العتيبي')
        ->assertSee('مكان هادئ ونظيف والتعامل راقي.')
        ->assertDontSee('تقييم قيد المراجعة يجب ألا يظهر.');
});
