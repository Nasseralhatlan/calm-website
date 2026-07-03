<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\AttributeGroup;

it('returns the amenity catalog publicly: groups + nested attributes in admin order', function (): void {
    $second = AttributeGroup::query()->create(['name_ar' => 'عام', 'name_en' => 'General', 'sort_order' => 2]);
    $first = AttributeGroup::query()->create(['name_ar' => 'مرافق', 'name_en' => 'Facilities', 'sort_order' => 1]);

    Attribute::query()->create([
        'group_id' => $first->id,
        'name_ar' => 'مسبح',
        'name_en' => 'Pool',
        'icon' => 'pool',
        'question_ar' => 'هل يوجد مسبح؟',
        'question_en' => 'Is there a pool?',
        'type' => 'select',
        'photo_rule' => 'required',
        'is_highlighted' => true,
        'sort_order' => 2,
        'options' => ['indoor', 'outdoor'],
    ]);
    Attribute::query()->create([
        'group_id' => $first->id,
        'name_ar' => 'واي فاي',
        'name_en' => 'WiFi',
        'type' => 'boolean',
        'sort_order' => 1,
    ]);

    $this->getJson('/api/attribute-groups')
        ->assertOk()
        ->assertJsonPath('status', 200)
        ->assertJsonCount(2, 'data')
        // Groups follow the admin-controlled sort_order.
        ->assertJsonPath('data.0.name_en', 'Facilities')
        ->assertJsonPath('data.1.name_en', 'General')
        // Attributes nest inside their group, also in admin order.
        ->assertJsonCount(2, 'data.0.attributes')
        ->assertJsonPath('data.0.attributes.0.name_en', 'WiFi')
        ->assertJsonPath('data.0.attributes.0.type', 'boolean')
        ->assertJsonPath('data.0.attributes.0.photo_rule', 'none')
        ->assertJsonPath('data.0.attributes.1.name_en', 'Pool')
        ->assertJsonPath('data.0.attributes.1.type', 'select')
        ->assertJsonPath('data.0.attributes.1.photo_rule', 'required')
        ->assertJsonPath('data.0.attributes.1.is_highlighted', true)
        ->assertJsonPath('data.0.attributes.1.options', ['indoor', 'outdoor'])
        ->assertJsonPath('data.0.attributes.1.question_en', 'Is there a pool?')
        // A group with no attributes still returns an (empty) list.
        ->assertJsonPath('data.1.attributes', []);
});

it('matches the seeded catalog the web wizard uses', function (): void {
    $this->seed();

    $response = $this->getJson('/api/attribute-groups')->assertOk();

    expect($response->json('data'))->toHaveCount(AttributeGroup::query()->count())
        ->and(collect($response->json('data'))->flatMap(fn (array $g) => $g['attributes']))
        ->toHaveCount(Attribute::query()->count());
});
