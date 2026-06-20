<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;

beforeEach(function (): void {
    $this->seed();
    $this->host = User::factory()->create(['phone' => '517100001']);
});

function imgPaths(int $n): array
{
    return array_map(fn (int $i): string => "places/uploads/img-{$i}.jpg", range(1, $n));
}

function createPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Image-rule place',
        'description' => 'x',
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 4,
        'location_url' => 'https://maps.google.com/?q=24.7,46.6',
        'extra_image_paths' => imgPaths(5),
    ], $overrides);
}

it('creates a place with exactly 5 images', function (): void {
    $this->actingAs($this->host, 'api')
        ->post('/host-register', createPayload())
        ->assertRedirect(route('user.places'));

    expect(Place::query()->latest('id')->first()->photos()->count())->toBe(5);
});

it('accepts checkout_next_day posted as "1" / "0" strings from the form checkbox', function (): void {
    $this->actingAs($this->host, 'api')
        ->post('/host-register', createPayload(['title' => 'Overnight', 'checkout_next_day' => '1']))
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('user.places'));
    expect(Place::query()->where('title', 'Overnight')->first()->checkout_next_day)->toBeTrue();

    $this->actingAs($this->host, 'api')
        ->post('/host-register', createPayload(['title' => 'Same day', 'checkout_next_day' => '0']))
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('user.places'));
    expect(Place::query()->where('title', 'Same day')->first()->checkout_next_day)->toBeFalse();
});

it('rejects a place with fewer than 5 images', function (): void {
    $this->actingAs($this->host, 'api')
        ->post('/host-register', createPayload(['extra_image_paths' => imgPaths(4)]))
        ->assertSessionHasErrors('images');

    expect(Place::query()->count())->toBe(0);
});

it('rejects a section with more than 10 images (the "other" section)', function (): void {
    $this->actingAs($this->host, 'api')
        ->post('/host-register', createPayload(['extra_image_paths' => imgPaths(11)]))
        ->assertSessionHasErrors('extra_image_paths');

    expect(Place::query()->count())->toBe(0);
});

it('rejects an amenity section with more than 10 images', function (): void {
    $attrId = Attribute::query()->create([
        'group_id' => AttributeGroup::query()->create(['name_en' => 'G', 'name_ar' => 'ج'])->id,
        'name_en' => 'Pool', 'name_ar' => 'مسبح', 'icon' => '🏊', 'type' => 'boolean', 'photo_rule' => 'optional',
    ])->id;

    $this->actingAs($this->host, 'api')
        ->post('/host-register', createPayload([
            'attributes' => [['attribute_id' => $attrId, 'value' => '1']],
            'attribute_image_paths' => [$attrId => imgPaths(11)],
            'extra_image_paths' => [],
        ]))
        ->assertSessionHasErrors("attribute_image_paths.{$attrId}");

    expect(Place::query()->count())->toBe(0);
});
