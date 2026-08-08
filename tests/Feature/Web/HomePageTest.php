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

it('renders the browse home: quick types, curated lists, and the tab bar for guests too', function (): void {
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
        // Guest hearts link to login; the app tab bar shows for everyone.
        ->assertSee('/login')
        ->assertSee(route('user.trips'))
        ->assertSee(route('user.favorites'))
        ->assertSee(route('user.account'));
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

it('shows the host-mode chip on home for hosts only', function (): void {
    homePagePlace($this->host);

    $this->actingAs($this->host, 'api')
        ->get('/')
        ->assertOk()
        ->assertSee(route('user.places'));

    $guest = User::factory()->create(['phone' => '516200002']);
    $this->actingAs($guest, 'api')
        ->get('/')
        ->assertOk()
        ->assertDontSee(route('user.places'));
});

it('gates the tab pages inline: prompts for guests, content when signed in', function (): void {
    // Guests get the page WITH a sign-in prompt (no redirect, like the app).
    $this->get('/favorites')->assertOk()->assertSee('تسجيل الدخول');
    $this->get('/trips')->assertOk()->assertSee('تسجيل الدخول');
    $this->get('/account')->assertOk()->assertSee('تسجيل الدخول');

    $this->actingAs($this->host, 'api')->get('/favorites')->assertOk()->assertSee('/api/favorites');
    $this->actingAs($this->host, 'api')->get('/trips')->assertOk()->assertSee('/api/bookings');
    $this->actingAs($this->host, 'api')->get('/account')->assertOk()->assertSee('تسجيل الخروج');
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
