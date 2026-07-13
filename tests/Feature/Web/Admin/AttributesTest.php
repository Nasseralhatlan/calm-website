<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $this->actingAs($this->admin, 'api');
    $this->group = AttributeGroup::query()->create(['name_ar' => 'مجموعة', 'name_en' => 'Group']);
});

function attrPayload(array $overrides = []): array
{
    return array_merge([
        'group_id' => test()->group->id,
        'name_ar' => 'واي فاي',
        'name_en' => 'WiFi',
        'type' => AttributeType::Boolean->value,
        'photo_rule' => 'none',
    ], $overrides);
}

it('stores an attribute with highlight + sort_order', function (): void {
    $this->post('/admin/attributes', attrPayload([
        'name_en' => 'Pool',
        'is_highlighted' => '1',
        'sort_order' => 5,
    ]))->assertRedirect('/admin/attributes')->assertSessionHas('status');

    $attr = Attribute::query()->where('name_en', 'Pool')->first();
    expect($attr->is_highlighted)->toBeTrue()
        ->and($attr->sort_order)->toBe(5);
});

it('defaults an unchecked highlight checkbox to false and blank sort to 0', function (): void {
    $this->post('/admin/attributes', attrPayload([
        'name_en' => 'Kitchen',
        // no is_highlighted key (unchecked), no sort_order
    ]))->assertRedirect('/admin/attributes');

    $attr = Attribute::query()->where('name_en', 'Kitchen')->first();
    expect($attr->is_highlighted)->toBeFalse()
        ->and($attr->sort_order)->toBe(0);
});

it('updates highlight + sort_order on an existing attribute', function (): void {
    $attr = Attribute::query()->create(attrPayload(['name_en' => 'AC', 'is_highlighted' => true, 'sort_order' => 9]));

    $this->put("/admin/attributes/{$attr->id}", attrPayload([
        'name_en' => 'AC',
        'sort_order' => 2,
        // is_highlighted unchecked → should flip to false
    ]))->assertRedirect('/admin/attributes');

    $attr->refresh();
    expect($attr->is_highlighted)->toBeFalse()
        ->and($attr->sort_order)->toBe(2);
});

it('rejects a sort_order above the max', function (): void {
    $this->post('/admin/attributes', attrPayload(['sort_order' => 10000]))
        ->assertSessionHasErrors('sort_order');
});

it('orders the admin list by sort_order then name', function (): void {
    Attribute::query()->create(attrPayload(['name_en' => 'Zebra', 'sort_order' => 1]));
    Attribute::query()->create(attrPayload(['name_en' => 'Apple', 'sort_order' => 2]));

    $ordered = Attribute::query()->ordered()->pluck('name_en')->all();
    expect($ordered)->toBe(['Zebra', 'Apple']); // sort_order wins over alphabetical
});

// ─── merged management page ──────────────────────────────────────────────────

it('renders the merged page with groups and their attributes', function (): void {
    Attribute::query()->create(attrPayload(['name_en' => 'WiFi', 'name_ar' => 'واي فاي']));

    $this->get('/admin/attributes')
        ->assertOk()
        ->assertSee($this->group->name_ar, escape: false)
        ->assertSee('واي فاي', escape: false);
});

it('toggles highlight via the JSON endpoint', function (): void {
    $attr = Attribute::query()->create(attrPayload(['name_en' => 'Pool']));

    $this->postJson("/admin/attributes/{$attr->id}/highlight")
        ->assertOk()
        ->assertJsonPath('is_highlighted', true);
    expect($attr->refresh()->is_highlighted)->toBeTrue();

    $this->postJson("/admin/attributes/{$attr->id}/highlight")
        ->assertOk()
        ->assertJsonPath('is_highlighted', false);
    expect($attr->refresh()->is_highlighted)->toBeFalse();
});

it('toggles a group\'s standalone flag via the JSON endpoint', function (): void {
    $this->postJson("/admin/attribute-groups/{$this->group->id}/standalone")
        ->assertOk()
        ->assertJsonPath('is_standalone', true);
    expect($this->group->refresh()->is_standalone)->toBeTrue();

    $this->postJson("/admin/attribute-groups/{$this->group->id}/standalone")
        ->assertOk()
        ->assertJsonPath('is_standalone', false);
    expect($this->group->refresh()->is_standalone)->toBeFalse();
});

it('creates, updates and deletes an attribute over JSON', function (): void {
    // Create
    $created = $this->postJson('/admin/attributes', attrPayload(['name_en' => 'Sauna', 'is_highlighted' => true]))
        ->assertCreated()
        ->assertJsonPath('attribute.name_en', 'Sauna')
        ->assertJsonPath('attribute.is_highlighted', true)
        ->json('attribute.id');

    // Update
    $this->putJson("/admin/attributes/{$created}", attrPayload(['name_en' => 'Sauna Room']))
        ->assertOk()
        ->assertJsonPath('attribute.name_en', 'Sauna Room')
        ->assertJsonPath('attribute.is_highlighted', false);

    // Delete
    $this->deleteJson("/admin/attributes/{$created}")->assertNoContent();
    expect(Attribute::query()->whereKey($created)->exists())->toBeFalse();
});

it('validates attribute JSON input (422)', function (): void {
    // The app wraps validation errors in its ApiResponse envelope: data.errors.*
    $this->postJson('/admin/attributes', ['name_en' => 'X'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['group_id', 'name_ar', 'type']]]);
});

it('creates and deletes a group over JSON', function (): void {
    $id = $this->postJson('/admin/attribute-groups', ['name_ar' => 'مرافق', 'name_en' => 'Facilities'])
        ->assertCreated()
        ->assertJsonPath('group.name_en', 'Facilities')
        ->json('group.id');

    $this->deleteJson("/admin/attribute-groups/{$id}")->assertNoContent();
    expect(AttributeGroup::query()->whereKey($id)->exists())->toBeFalse();
});

it('persists group order and a running attribute order across groups', function (): void {
    $groupB = AttributeGroup::query()->create(['name_ar' => 'ب', 'name_en' => 'B Group']);
    $b1 = Attribute::query()->create(attrPayload(['group_id' => $groupB->id, 'name_en' => 'B1']));
    $b2 = Attribute::query()->create(attrPayload(['group_id' => $groupB->id, 'name_en' => 'B2']));
    $a1 = Attribute::query()->create(attrPayload(['group_id' => $this->group->id, 'name_en' => 'A1']));
    $a2 = Attribute::query()->create(attrPayload(['group_id' => $this->group->id, 'name_en' => 'A2']));

    // Order: groupB first (b2 then b1), then the original group (a1 then a2).
    $payload = json_encode([
        ['id' => $groupB->id, 'attributes' => [$b2->id, $b1->id]],
        ['id' => $this->group->id, 'attributes' => [$a1->id, $a2->id]],
    ]);

    $this->post('/admin/attributes/reorder', ['payload' => $payload])
        ->assertRedirect('/admin/attributes')
        ->assertSessionHas('status');

    expect($groupB->refresh()->sort_order)->toBe(0)
        ->and($this->group->refresh()->sort_order)->toBe(1)
        // Running index across groups in display order: b2,b1,a1,a2 → 0,1,2,3.
        ->and($b2->refresh()->sort_order)->toBe(0)
        ->and($b1->refresh()->sort_order)->toBe(1)
        ->and($a1->refresh()->sort_order)->toBe(2)
        ->and($a2->refresh()->sort_order)->toBe(3);
});

it('rejects a reorder payload with an unknown group id', function (): void {
    $payload = json_encode([['id' => '11111111-1111-1111-1111-111111111111', 'attributes' => []]]);

    $this->post('/admin/attributes/reorder', ['payload' => $payload])
        ->assertSessionHasErrors('groups.0.id');
});

it('persists the standalone flag on groups and defaults it off when omitted', function (): void {
    // Create with the flag on.
    $id = $this->postJson('/admin/attribute-groups', [
        'name_ar' => 'الجلسات الخارجية', 'name_en' => 'Outdoor seating', 'is_standalone' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('group.is_standalone', true)
        ->json('group.id');

    expect(AttributeGroup::query()->find($id)->is_standalone)->toBeTrue();

    // Updating WITHOUT the field (unchecked checkbox) flips it back off.
    $this->putJson("/admin/attribute-groups/{$id}", ['name_ar' => 'الجلسات', 'name_en' => 'Seating'])
        ->assertOk()
        ->assertJsonPath('group.is_standalone', false);

    expect(AttributeGroup::query()->find($id)->is_standalone)->toBeFalse();
});
