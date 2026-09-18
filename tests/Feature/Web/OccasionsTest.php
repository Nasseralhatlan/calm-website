<?php

declare(strict_types=1);

use App\Enums\OccasionRequestStatus;
use App\Enums\UserRole;
use App\Models\OccasionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a signed-out visitor open the wizard', function (): void {
    // Filling it is public; only submitting needs an account.
    $this->get('/occasions')
        ->assertOk()
        ->assertSee('ما نوع مناسبتك؟', false)
        ->assertSee('كم عدد الضيوف المتوقع؟', false);
});

it('offers every catalogue option the app offers', function (): void {
    $page = $this->get('/occasions')->assertOk();

    foreach (array_keys(config('occasions.types')) as $key) {
        $page->assertSee("pickType('{$key}')", false);
    }

    foreach (array_keys(config('occasions.needs')) as $key) {
        $page->assertSee("toggleNeed('{$key}')", false);
    }
});

it('shows the occasions card on the home page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('خدمة المناسبات', false)
        ->assertSee('/occasions', false);
});

describe('admin queue', function (): void {
    it('lists leads with the guest\'s phone so the team can call', function (): void {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $guest = User::factory()->create(['name' => 'Nasser', 'phone' => '966500000001']);

        OccasionRequest::create([
            'user_id' => $guest->id,
            'occasion_type' => 'wedding',
            'details' => ['guests' => 300, 'needs' => ['coffee'], 'venue_status' => 'help'],
            'status' => OccasionRequestStatus::New,
        ]);

        $this->actingAs($admin, 'api')
            ->get('/admin/occasion-requests')
            ->assertOk()
            ->assertSee('Nasser')
            ->assertSee('966500000001')
            // Stored keys are rendered as readable labels, not raw keys.
            ->assertSee('صبّابين', false)
            ->assertSee('ساعدوني في إيجاد مكان', false);
    });

    it('moves a lead along the pipeline', function (): void {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $request = OccasionRequest::create([
            'user_id' => User::factory()->create()->id,
            'occasion_type' => 'birthday',
            'details' => ['guests' => 20],
            'status' => OccasionRequestStatus::New,
        ]);

        $this->actingAs($admin, 'api')
            ->post("/admin/occasion-requests/{$request->id}/status", ['status' => 'ongoing'])
            ->assertRedirect();

        expect($request->fresh()->status)->toBe(OccasionRequestStatus::Ongoing);
    });

    it('filters the queue by status', function (): void {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $guest = User::factory()->create();

        OccasionRequest::create([
            'user_id' => $guest->id, 'occasion_type' => 'wedding',
            'details' => ['guests' => 300], 'status' => OccasionRequestStatus::New,
        ]);
        OccasionRequest::create([
            'user_id' => $guest->id, 'occasion_type' => 'graduation',
            'details' => ['guests' => 40], 'status' => OccasionRequestStatus::Completed,
        ]);

        $this->actingAs($admin, 'api')
            ->get('/admin/occasion-requests?status=completed')
            ->assertOk()
            ->assertSee('تخرّج', false)
            ->assertDontSee('زواج', false);
    });

    it('keeps the queue away from non-admins', function (): void {
        $this->actingAs(User::factory()->create(), 'api')
            ->get('/admin/occasion-requests')
            ->assertRedirect();
    });
});

it('tracks occasion requests on the bookings page', function (): void {
    // The wizard's confirmation promises «متابعة طلبك في صفحة الحجوزات» —
    // this is that page holding up its end.
    $me = User::factory()->create();

    $page = $this->actingAs($me, 'api')->get('/trips')->assertOk();

    $page->assertSee('طلبات المناسبات', false)   // section heading
        ->assertSee('loadOccasions()', false)     // fetch is wired into x-init
        ->assertSee('/api/occasion-requests', false)
        // The catalogue is inlined so a stored key renders as a label + emoji.
        ->assertSee('birthday', false);
});

it('shows a guest the sign-in prompt on the bookings page', function (): void {
    $this->get('/trips')
        ->assertOk()
        ->assertDontSee('loadOccasions()', false);
});
