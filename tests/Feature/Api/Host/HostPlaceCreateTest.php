<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
    $this->host = User::factory()->create(['phone' => '514100001']);
    // Photo urls resolve through the s3 disk — point it at a fake CDN.
    config()->set('filesystems.disks.s3.url', 'https://cdn.calm.test');
});

function hostPlacePayload(array $overrides = []): array
{
    return array_merge([
        'title_ar' => 'شاليه الاختبار',
        'title_en' => 'Test Chalet',
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'price' => 750,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 6,
        'location_url' => 'https://maps.google.com/?q=24.7,46.6',
        'extra_image_paths' => ['p/1.jpg', 'p/2.jpg', 'p/3.jpg', 'p/4.jpg', 'p/5.jpg'],
        'featured' => ['extra_images.0', 'extra_images.1'],
    ], $overrides);
}

it('auto-saves a draft and keeps patching the same row', function (): void {
    // First save — no draft_id yet.
    $first = $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places/draft', [
            'place_type_id' => PlaceType::query()->first()->id,
            'title_ar' => 'مسودة',
            'last_step' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.review_status', 'draft');

    $id = $first->json('data.id');
    expect($id)->toBeString();

    // Subsequent saves carry the returned id and update the same record.
    $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places/draft', [
            'draft_id' => $id,
            'place_type_id' => PlaceType::query()->first()->id,
            'title_ar' => 'مسودة محدثة',
            'price' => 300,
            'last_step' => 4,
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $id);

    $places = Place::query()->where('host_user_id', $this->host->id)->get();
    expect($places)->toHaveCount(1)
        ->and($places->first()->title_ar)->toBe('مسودة محدثة')
        ->and($places->first()->price)->toBe(300)
        ->and((int) $places->first()->last_step)->toBe(4);
});

it('submits a place for review with at least 5 photos', function (): void {
    $response = $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places', hostPlacePayload())
        ->assertCreated()
        ->assertJsonPath('data.review_status', 'pending_review')
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.title_en', 'Test Chalet');

    expect($response->json('data.photos'))->toHaveCount(5)
        ->and($response->json('data.photos.0.path'))->toBe('p/1.jpg')
        // First featured marker = the cover.
        ->and($response->json('data.photos.0.featured_order'))->toBe(0);
});

it('promotes an existing draft on submit instead of creating a new row', function (): void {
    $draftId = $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places/draft', ['place_type_id' => PlaceType::query()->first()->id])
        ->json('data.id');

    $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places', hostPlacePayload(['draft_id' => $draftId]))
        ->assertCreated()
        ->assertJsonPath('data.id', $draftId)
        ->assertJsonPath('data.review_status', 'pending_review');

    expect(Place::query()->where('host_user_id', $this->host->id)->count())->toBe(1);
});

it('rejects a submit with fewer than 5 photos', function (): void {
    $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places', hostPlacePayload([
            'extra_image_paths' => ['p/1.jpg', 'p/2.jpg'],
        ]))
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['images']]]);

    expect(Place::query()->where('host_user_id', $this->host->id)->exists())->toBeFalse();
});

it('persists amenities and per-amenity photos on submit', function (): void {
    $attribute = Attribute::query()->first();

    $response = $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places', hostPlacePayload([
            'attributes' => [
                ['attribute_id' => $attribute->id, 'value' => '1', 'description' => 'Great one'],
            ],
            'attribute_image_paths' => [$attribute->id => ['a/1.jpg', 'a/2.jpg']],
            'extra_image_paths' => ['p/1.jpg', 'p/2.jpg', 'p/3.jpg'],
            'featured' => ["attribute_images.{$attribute->id}.0", 'extra_images.0'],
        ]))
        ->assertCreated();

    expect($response->json('data.attributes'))->toHaveCount(1)
        ->and($response->json('data.attributes.0.attribute_id'))->toBe($attribute->id)
        ->and($response->json('data.attributes.0.description'))->toBe('Great one')
        ->and($response->json('data.photos'))->toHaveCount(5);

    $photos = collect($response->json('data.photos'));
    expect($photos->firstWhere('featured_order', 0)['path'])->toBe('a/1.jpg')
        ->and($photos->firstWhere('featured_order', 0)['place_attribute_id'])->toBe($attribute->id)
        ->and($photos->firstWhere('featured_order', 1)['path'])->toBe('p/1.jpg');
});

it('requires authentication', function (): void {
    $this->postJson('/api/host/places/draft', [])->assertStatus(401);
    $this->postJson('/api/host/places', [])->assertStatus(401);
});

it('stores the map pin and derives the location url from it', function (): void {
    // Pin without a pasted URL — the server derives the canonical link.
    $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places', hostPlacePayload([
            'location_url' => null,
            'latitude' => 24.7135517,
            'longitude' => 46.6752957,
        ]))
        ->assertCreated();

    $place = Place::query()->latest('created_at')->first();
    expect((float) $place->latitude)->toBe(24.7135517)
        ->and((float) $place->longitude)->toBe(46.6752957)
        ->and($place->location_url)->toBe('https://maps.google.com/?q=24.7135517,46.6752957');
});

it('rejects out-of-range or half-missing coordinates', function (): void {
    $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places', hostPlacePayload(['latitude' => 95, 'longitude' => 46.6]))
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['latitude']]]);

    // Latitude without longitude — the pin is always a pair.
    $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places', hostPlacePayload(['latitude' => 24.7]))
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['longitude']]]);

    // Neither a URL nor a pin → the location step is incomplete.
    $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places', hostPlacePayload(['location_url' => null]))
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['location_url']]]);
});

it('exposes coordinates by tier: approximate publicly, exact to the owner', function (): void {
    $this->actingAs($this->host, 'api')
        ->postJson('/api/host/places', hostPlacePayload([
            'latitude' => 24.7135517,
            'longitude' => 46.6752957,
        ]))
        ->assertCreated();

    $place = Place::query()->latest('created_at')->first();
    $place->update(['status' => 'active', 'review_status' => 'approved']);

    // Owner (edit/resume): the exact pin.
    $this->actingAs($this->host, 'api')
        ->getJson("/api/host/places/{$place->id}")
        ->assertOk()
        ->assertJsonPath('data.latitude', 24.7135517)
        ->assertJsonPath('data.longitude', 46.6752957);

    // Public detail: rounded to ~1 km — never the exact door.
    $this->getJson("/api/places/{$place->id}")
        ->assertOk()
        ->assertJsonPath('data.latitude', 24.71)
        ->assertJsonPath('data.longitude', 46.68);
});
