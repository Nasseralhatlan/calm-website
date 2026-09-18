<?php

declare(strict_types=1);

use App\Enums\OccasionRequestStatus;
use App\Mail\OwnerAlert;
use App\Models\OccasionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    config()->set('owner.emails', ['events@calmapp.co']);
});

it('records an occasion request and alerts the events team', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/occasion-requests', [
            'occasion_type' => 'wedding',
            'needs' => ['coffee', 'styling'],
            'guests' => 200,
            'venue_status' => 'help',
            'notes' => 'Outdoor if possible',
        ])
        ->assertCreated()
        ->assertJsonPath('data.occasion_type', 'wedding')
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.details.guests', 200)
        ->assertJsonPath('data.details.venue_status', 'help');

    $request = OccasionRequest::sole();

    expect($request->user_id)->toBe($user->id)
        ->and($request->occasion_type)->toBe('wedding')
        ->and($request->details['needs'])->toBe(['coffee', 'styling'])
        ->and($request->details['notes'])->toBe('Outdoor if possible');

    Mail::assertQueued(OwnerAlert::class);
});

it('keeps the free text when the occasion type is other', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/occasion-requests', [
            'occasion_type' => 'other',
            'occasion_type_other' => 'Baby shower',
            'guests' => 15,
        ])
        ->assertCreated()
        ->assertJsonPath('data.details.occasion_type_other', 'Baby shower');
});

it('drops answers the guest skipped so the stored blob stays readable', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/occasion-requests', [
            'occasion_type' => 'birthday',
            'guests' => 10,
        ])
        ->assertCreated();

    // Only the questions they actually answered survive.
    expect(array_keys(OccasionRequest::sole()->details))->toBe(['guests']);
});

it('requires an occasion type and at least one guest', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/occasion-requests', ['guests' => 0])
        ->assertStatus(422)
        ->assertJsonPath('data.errors.occasion_type.0', 'The occasion type field is required.');
});

it('rejects an anonymous submission', function (): void {
    $this->postJson('/api/occasion-requests', [
        'occasion_type' => 'birthday',
        'guests' => 10,
    ])->assertStatus(401);
});

it('lists only the signed-in guest\'s own requests, newest first', function (): void {
    $me = User::factory()->create();
    $someoneElse = User::factory()->create();

    OccasionRequest::create([
        'user_id' => $me->id, 'occasion_type' => 'birthday',
        'details' => ['guests' => 10], 'status' => 'new',
    ]);
    OccasionRequest::create([
        'user_id' => $me->id, 'occasion_type' => 'wedding',
        'details' => ['guests' => 300], 'status' => 'ongoing',
    ]);
    OccasionRequest::create([
        'user_id' => $someoneElse->id, 'occasion_type' => 'graduation',
        'details' => ['guests' => 50], 'status' => 'new',
    ]);

    $response = $this->actingAs($me, 'api')
        ->getJson('/api/occasion-requests')
        ->assertOk()
        ->assertJsonCount(2, 'data.items');

    // Newest first, and never another guest's lead.
    expect($response->json('data.items.0.occasion_type'))->toBe('wedding')
        ->and($response->json('data.items.0.status'))->toBe('ongoing')
        ->and(collect($response->json('data.items'))->pluck('occasion_type'))
        ->not->toContain('graduation');
});

it('returns every field the trips page and the app render', function (): void {
    $me = User::factory()->create();
    OccasionRequest::create([
        'user_id' => $me->id,
        'occasion_type' => 'wedding',
        'details' => ['guests' => 250, 'needs' => ['coffee']],
        'status' => OccasionRequestStatus::Ongoing,
    ]);

    $r = $this->actingAs($me, 'api')->getJson('/api/occasion-requests')->assertOk();

    expect($r->json('data.items.0'))
        ->toHaveKeys(['id', 'occasion_type', 'details', 'status', 'status_label', 'created_at'])
        // status_label saves every client from shipping its own status dictionary.
        ->and($r->json('data.items.0.status_label'))->toBe('جارٍ التنفيذ')
        ->and($r->json('data.items.0.details.guests'))->toBe(250);
});
