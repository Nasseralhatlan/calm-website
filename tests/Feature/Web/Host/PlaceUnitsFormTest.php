<?php

declare(strict_types=1);

use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
    $this->host = User::factory()->create(['phone' => '517300301']);
});

/**
 * The exact shape the wizard FORM posts (hidden mirrors) — units included.
 *
 * @return array<string, mixed>
 */
function unitsFormPayload(array $overrides = []): array
{
    return array_merge([
        'title_ar' => 'مكان الوحدات',
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'price' => 700,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 6,
        'location_url' => 'https://maps.google.com/?q=24.7,46.6',
        'extra_image_paths' => [
            'places/uploads/1.jpg', 'places/uploads/2.jpg', 'places/uploads/3.jpg',
            'places/uploads/4.jpg', 'places/uploads/5.jpg',
        ],
        'units' => [
            ['id' => null, 'name' => 'وحدة ١'],
            ['id' => null, 'name' => 'وحدة ٢'],
        ],
    ], $overrides);
}

it('saves units on the wizard form submit (create)', function (): void {
    $this->actingAs($this->host, 'api')
        ->post('/host-register', unitsFormPayload())
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('user.places'));

    $place = Place::query()->latest('created_at')->first();
    expect($place->units()->pluck('name')->all())->toBe(['وحدة ١', 'وحدة ٢']);
});

it('keeps, renames and adds units on the host edit form submit', function (): void {
    $this->actingAs($this->host, 'api')
        ->post('/host-register', unitsFormPayload())
        ->assertRedirect();
    $place = Place::query()->latest('created_at')->first();
    $keep = $place->units()->where('name', 'وحدة ١')->first();

    $this->actingAs($this->host, 'api')
        ->put(route('host.places.update', $place), unitsFormPayload([
            'units' => [
                ['id' => $keep->id, 'name' => 'وحدة أ'],  // renamed, same row
                ['id' => null, 'name' => 'وحدة ٣'],        // new; وحدة ٢ dropped
            ],
        ]))
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('user.places'));

    $names = $place->refresh()->units()->pluck('name', 'id');
    expect($names->all())->toBe([$keep->id => 'وحدة أ'] + $names->except($keep->id)->all())
        ->and($names)->toHaveCount(2)
        ->and($names->values()->all())->toContain('وحدة ٣')
        ->and($names->values()->all())->not->toContain('وحدة ٢');
});
