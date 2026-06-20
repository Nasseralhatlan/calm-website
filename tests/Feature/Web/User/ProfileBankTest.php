<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed();
    $this->user = User::factory()->create(['phone' => '512000001', 'name' => 'Old Name']);
});

it('uploads an avatar from the profile page', function (): void {
    Storage::fake('s3');

    $this->actingAs($this->user, 'api')
        ->patch('/profile', ['avatar' => UploadedFile::fake()->image('me.jpg', 300, 300)])
        ->assertRedirect();

    $this->user->refresh();
    expect($this->user->avatar)->not->toBeNull();
    Storage::disk('s3')->assertExists($this->user->avatar);
});

it('shows the profile edit form with the bank fields', function (): void {
    $this->actingAs($this->user, 'api')
        ->get('/profile')
        ->assertOk()
        ->assertSee('name="bank"', false)           // free-text bank input
        ->assertSee('name="bank_account"', false)   // IBAN input
        ->assertSee($this->user->phone);            // phone shown (read-only)
});

it('saves the bank name and IBAN from the profile page', function (): void {
    $this->actingAs($this->user, 'api')
        ->patch('/profile', [
            'bank' => 'Al Rajhi Bank',
            'bank_account' => 'SA0380000000608010167519',
        ])
        ->assertRedirect();

    $this->user->refresh();
    expect($this->user->bank)->toBe('Al Rajhi Bank')
        ->and($this->user->bank_account)->toBe('SA0380000000608010167519');
});

it('normalises the IBAN — strips spaces and upper-cases', function (): void {
    $this->actingAs($this->user, 'api')
        ->patch('/profile', [
            'bank' => 'Alinma',
            'bank_account' => 'sa03 8000 0000 6080 1016 7519',
        ])
        ->assertRedirect();

    expect($this->user->refresh()->bank_account)->toBe('SA0380000000608010167519');
});

it('rejects an invalid IBAN', function (): void {
    $this->actingAs($this->user, 'api')
        ->from('/profile')
        ->patch('/profile', ['bank' => 'Some Bank', 'bank_account' => '12345'])
        ->assertSessionHasErrors('bank_account');

    expect($this->user->refresh()->bank_account)->toBeNull();
});

it('edits profile fields but never the phone', function (): void {
    $original = $this->user->phone;

    $this->actingAs($this->user, 'api')
        ->patch('/profile', ['name' => 'New Name', 'phone' => '599999999'])
        ->assertRedirect();

    $this->user->refresh();
    expect($this->user->name)->toBe('New Name')
        ->and($this->user->phone)->toBe($original); // phone untouched
});
