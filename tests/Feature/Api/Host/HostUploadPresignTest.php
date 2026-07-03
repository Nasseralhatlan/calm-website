<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    // Fake bucket credentials — presigning is pure local crypto (no network
    // call), it only needs key/secret/region/bucket to build the signed URL.
    config()->set('filesystems.disks.s3.key', 'test-key');
    config()->set('filesystems.disks.s3.secret', 'test-secret');
    config()->set('filesystems.disks.s3.region', 'us-east-1');
    config()->set('filesystems.disks.s3.bucket', 'calm-test');
    config()->set('filesystems.disks.s3.url', 'https://cdn.calm.test');
});

it('mints a presigned PUT with a server-side key under places/uploads/', function (): void {
    $host = User::factory()->create();

    $response = $this->actingAs($host, 'api')
        ->postJson('/api/host/uploads/presign', ['filename' => 'My Photo.JPG', 'mime' => 'image/jpeg'])
        ->assertOk()
        ->assertJsonPath('data.mime', 'image/jpeg');

    $path = $response->json('data.path');
    expect($path)->toStartWith('places/uploads/')
        ->and($path)->toEndWith('.jpg') // extension lowercased, name replaced by a random key
        ->and($response->json('data.put_url'))->toContain('X-Amz-Signature')
        ->and($response->json('data.put_url'))->toContain('calm-test')
        ->and($response->json('data.public_url'))->toBe('https://cdn.calm.test/'.$path);
});

it('defaults a missing extension to jpg', function (): void {
    $host = User::factory()->create();

    $response = $this->actingAs($host, 'api')
        ->postJson('/api/host/uploads/presign', ['filename' => 'no-extension', 'mime' => 'image/jpeg'])
        ->assertOk();

    expect($response->json('data.path'))->toEndWith('.jpg');
});

it('validates filename and mime', function (): void {
    $host = User::factory()->create();

    $this->actingAs($host, 'api')
        ->postJson('/api/host/uploads/presign', [])
        ->assertStatus(422)
        ->assertJsonStructure(['data' => ['errors' => ['filename', 'mime']]]);
});

it('requires authentication', function (): void {
    $this->postJson('/api/host/uploads/presign', ['filename' => 'a.jpg', 'mime' => 'image/jpeg'])
        ->assertStatus(401);
});
