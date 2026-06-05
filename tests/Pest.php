<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

uses(RefreshDatabase::class)->in('Feature');

/*
 * Global stray-request guard.
 *
 * Any HTTP call made via the `Http` facade during a test that has NOT been
 * explicitly registered with `Http::fake([...])` will throw rather than
 * actually go out the wire. This protects against:
 *   - the real SMS gateway being hit by an integration test that forgot to
 *     fake the gateway URL,
 *   - any future third-party API call we add slipping out unnoticed.
 *
 * Tests that legitimately exercise external HTTP (e.g. SmsSaudiDeliveryTest)
 * register their own `Http::fake([...])` rules for the specific URLs they hit.
 * Anything outside those rules → loud failure, not a silent real call.
 */
uses()->beforeEach(function (): void {
    Http::preventStrayRequests();
})->in('Feature', 'Unit');
