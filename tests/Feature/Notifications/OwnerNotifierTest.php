<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Mail\OwnerAlert;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Services\Notification\OwnerNotifier;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed();
});

function ownerBooking(): Booking
{
    $host = User::factory()->create(['phone' => '513900001']);
    $guest = User::factory()->create(['phone' => '513900002']);
    $place = Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Owner Place', 'price' => 500, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 2, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);

    return Booking::query()->create([
        'place_id' => $place->id, 'guest_user_id' => $guest->id, 'host_user_id' => $host->id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2, 'booking_price' => 100000, 'quantity' => 2, 'booking_amount' => 200000,
        'commission_rate' => 10, 'commission_amount' => 20000, 'vat_rate' => 15, 'vat_amount' => 30000,
        'total' => 230000, 'payout_status' => 'not_paid',
    ]);
}

it('emails the owners on a paid booking when OWNER_EMAILS is set', function (): void {
    config(['owner.emails' => ['boss@calm.sa', 'ops@calm.sa']]);
    Mail::fake();

    app(OwnerNotifier::class)->bookingPaid(ownerBooking());

    Mail::assertQueued(OwnerAlert::class, function (OwnerAlert $mail): bool {
        return str_contains($mail->subjectLine, 'New booking')
            && $mail->hasTo('boss@calm.sa')
            && $mail->hasTo('ops@calm.sa');
    });
});

it('does nothing when OWNER_EMAILS is empty', function (): void {
    config(['owner.emails' => []]);
    Mail::fake();

    app(OwnerNotifier::class)->bookingPaid(ownerBooking());

    Mail::assertNothingQueued();
});
