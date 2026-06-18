<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('updates the authenticated user via PATCH /api/user', function (): void {
    $user = User::factory()->create([
        'phone' => '512345678',
        'name' => null,
        'gender' => null,
    ]);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/user', [
            'name' => 'Nasser',
            'gender' => 'male',
            'age' => 30,
        ])
        ->assertOk()
        ->assertJsonPath('status', 200)
        ->assertJsonPath('data.name', 'Nasser')
        ->assertJsonPath('data.gender', 'male')
        ->assertJsonPath('data.age', 30);

    $fresh = User::find($user->id);
    expect($fresh->name)->toBe('Nasser');
    expect($fresh->gender)->toBe('male');
    expect($fresh->age)->toBe(30);
});

it('rejects an invalid email on PATCH /api/user', function (): void {
    $user = User::factory()->create(['phone' => '512345678']);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/user', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['email']]]);
});

it('ignores role attempts on PATCH /api/user', function (): void {
    $user = User::factory()->create(['phone' => '512345678', 'role' => 'user']);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/user', ['role' => 'admin', 'name' => 'Test'])
        ->assertOk();

    expect(User::find($user->id)->role->value)->toBe('user');
});

it('rejects unauthenticated PATCH /api/user', function (): void {
    $this->patchJson('/api/user', ['name' => 'Test'])->assertStatus(401);
});

it('uploads a profile picture + name via multipart POST /api/user', function (): void {
    Storage::fake('s3');
    $user = User::factory()->create(['phone' => '512345678', 'name' => null]);
    $token = auth('api')->login($user);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->post('/api/user', [
            'name' => 'Nasser',
            'avatar' => UploadedFile::fake()->image('me.jpg', 800, 800),
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nasser');

    // The response exposes a usable avatar URL...
    expect($response->json('data.avatar_url'))->toBeString()->not->toBeEmpty();

    // ...and the file actually landed on the (faked) S3 disk under avatars/.
    $fresh = User::find($user->id);
    expect($fresh->avatar)->toStartWith('avatars/');
    Storage::disk('s3')->assertExists($fresh->avatar);
});

it('returns avatar_url from GET /api/user', function (): void {
    Storage::fake('s3');
    $user = User::factory()->create(['phone' => '512345678', 'avatar' => 'avatars/x.jpg']);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonStructure(['data' => ['avatar_url']]);
});

it('replaces the old avatar on a new upload', function (): void {
    Storage::fake('s3');
    Storage::disk('s3')->put('avatars/old.jpg', 'x', 'public');
    $user = User::factory()->create(['phone' => '512345678', 'avatar' => 'avatars/old.jpg']);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->post('/api/user', ['avatar' => UploadedFile::fake()->image('new.png')])
        ->assertOk();

    Storage::disk('s3')->assertMissing('avatars/old.jpg');     // old one cleaned up
    Storage::disk('s3')->assertExists(User::find($user->id)->avatar);
});

it('rejects a non-image avatar', function (): void {
    Storage::fake('s3');
    $user = User::factory()->create(['phone' => '512345678']);
    $token = auth('api')->login($user);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->post('/api/user', ['avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf')])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['avatar']]]);
});
