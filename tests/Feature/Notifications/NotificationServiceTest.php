<?php

declare(strict_types=1);

use App\Contracts\PushDeliveryContract;
use App\Contracts\SmsDeliveryContract;
use App\Enums\BookingStatus;
use App\Enums\PlaceReviewStatus;
use App\Enums\PlaceStatus;
use App\Models\Booking;
use App\Models\CityArea;
use App\Models\DeviceToken;
use App\Models\NotificationBroadcast;
use App\Models\Place;
use App\Models\PlaceType;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notification\NotificationService;
use App\Services\Place\PlaceReviewService;
use App\Services\Place\PlaceService;

/** Recording fake SMS transport. */
function fakeSms(): object
{
    $sms = new class implements SmsDeliveryContract
    {
        /** @var list<array{phone:string,message:string}> */
        public array $calls = [];

        public function send(string $phone, string $message): void
        {
            $this->calls[] = ['phone' => $phone, 'message' => $message];
        }
    };
    app()->instance(SmsDeliveryContract::class, $sms);

    return $sms;
}

/** Recording fake push transport. */
function fakePush(): object
{
    $push = new class implements PushDeliveryContract
    {
        /** @var list<array{tokens:list<string>,title:string,body:string,data:array}> */
        public array $calls = [];

        public function send(array $tokens, string $title, string $body, array $data = []): void
        {
            $this->calls[] = ['tokens' => $tokens, 'title' => $title, 'body' => $body, 'data' => $data];
        }
    };
    app()->instance(PushDeliveryContract::class, $push);

    return $push;
}

beforeEach(function (): void {
    $this->seed();
    $this->service = app(NotificationService::class);
});

/** A confirmed booking with all required columns. */
function newBooking(Place $place, User $guest): Booking
{
    return Booking::query()->create([
        'place_id' => $place->id,
        'guest_user_id' => $guest->id,
        'host_user_id' => $place->host_user_id,
        'booking_status' => BookingStatus::Confirmed->value,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'guests' => 2,
        'booking_price' => 100000,
        'quantity' => 2,
        'booking_amount' => 200000,
        'commission_rate' => 10,
        'commission_amount' => 20000,
        'vat_rate' => 15,
        'vat_amount' => 30000,
        'total' => 230000,
        'payout_status' => 'not_paid',
    ]);
}

/**
 * @return array<string, mixed>
 */
function samplePayload(): array
{
    return [
        'type' => 'broadcast',
        'title_ar' => 'عنوان',
        'title_en' => 'Title',
        'body_ar' => 'نص عربي',
        'body_en' => 'English body',
        'data' => ['k' => 'v'],
    ];
}

it('writes one in-app row and fires SMS + push to every device', function (): void {
    config(['push.enabled' => true]); // push is off by default; opt in to test the channel
    $sms = fakeSms();
    $push = fakePush();
    $user = User::factory()->create(['phone' => '513000001', 'locale' => 'ar']);
    DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[a]']);
    DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[b]']);

    $this->service->notify($user, samplePayload());

    // In-app row (bilingual, unread).
    $row = UserNotification::query()->where('user_id', $user->id)->sole();
    expect($row->title_ar)->toBe('عنوان')
        ->and($row->title_en)->toBe('Title')
        ->and($row->read_at)->toBeNull();

    // SMS once, in the user's language (ar).
    expect($sms->calls)->toHaveCount(1)
        ->and($sms->calls[0]['phone'])->toBe('513000001')
        ->and($sms->calls[0]['message'])->toContain('عنوان');

    // Push once to both tokens, ar title.
    expect($push->calls)->toHaveCount(1)
        ->and($push->calls[0]['tokens'])->toEqualCanonicalizing(['ExponentPushToken[a]', 'ExponentPushToken[b]'])
        ->and($push->calls[0]['title'])->toBe('عنوان');
});

it('always delivers Arabic on SMS + push regardless of user locale', function (): void {
    config(['push.enabled' => true]);
    $sms = fakeSms();
    $push = fakePush();
    // Even an en-locale user receives Arabic on the outbound channels for now.
    $user = User::factory()->create(['phone' => '513000002', 'locale' => 'en']);
    DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[c]']);

    $this->service->notify($user, samplePayload());

    expect($sms->calls[0]['message'])->toContain('عنوان')
        ->and($sms->calls[0]['message'])->not->toContain('Title')
        ->and($push->calls[0]['title'])->toBe('عنوان');
});

it('still delivers in-app + SMS when the user has no device token (push skipped)', function (): void {
    config(['push.enabled' => true]);
    $sms = fakeSms();
    $push = fakePush();
    $user = User::factory()->create(['phone' => '513000003']);

    $this->service->notify($user, samplePayload());

    expect(UserNotification::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($sms->calls)->toHaveCount(1)
        ->and($push->calls)->toHaveCount(0);
});

it('does NOT send push when the channel is disabled (default), but still does SMS + in-app', function (): void {
    // push.enabled is false by default — even a user with a device token gets no push.
    $sms = fakeSms();
    $push = fakePush();
    $user = User::factory()->create(['phone' => '513000099']);
    DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'ExponentPushToken[off]']);

    $this->service->notify($user, samplePayload());

    expect($push->calls)->toHaveCount(0)                                          // push disabled
        ->and($sms->calls)->toHaveCount(1)                                        // SMS still sent
        ->and(UserNotification::query()->where('user_id', $user->id)->count())->toBe(1); // in-app still written
});

it('broadcasts to the chosen audience and records an audit row', function (): void {
    fakeSms();
    fakePush();
    $admin = User::factory()->create(['phone' => '599000001']);
    $host = User::factory()->create(['phone' => '513000010']);
    Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'A place', 'price' => 500, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 2, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
    User::factory()->create(['phone' => '513000011']); // a guest (no place)

    $broadcast = $this->service->broadcast($admin, 'hosts', samplePayload());

    expect($broadcast)->toBeInstanceOf(NotificationBroadcast::class)
        ->and($broadcast->recipients_count)->toBe(1)                         // only the host
        ->and(UserNotification::query()->where('user_id', $host->id)->where('type', 'broadcast')->count())->toBe(1);
});

it('notifies the host when their place is approved', function (): void {
    fakeSms();
    fakePush();
    $host = User::factory()->create(['phone' => '513000020']);
    $place = Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Lakeview', 'price' => 500, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 2, 'status' => PlaceStatus::Inactive->value, 'review_status' => PlaceReviewStatus::PendingReview->value,
    ]);

    app(PlaceReviewService::class)->approve($place);

    $row = UserNotification::query()->where('user_id', $host->id)->sole();
    expect($row->type)->toBe('place_approved')
        ->and($row->title_en)->toBe('Your place was approved')
        ->and($row->data['place_id'])->toBe($place->id);
});

it('notifies the host when their place is submitted for review', function (): void {
    $sms = fakeSms();
    fakePush();
    $host = User::factory()->create(['phone' => '513000030', 'locale' => 'en']);

    $place = app(PlaceService::class)->createForHost($host, [
        'title' => 'Seaside Cabin',
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'price' => 500,
        'check_in_time' => '15:00',
        'check_out_time' => '12:00',
        'max_guests' => 2,
    ]);

    $row = UserNotification::query()->where('user_id', $host->id)->where('type', 'place_submitted')->sole();
    expect($row->title_en)->toBe('Your place was submitted for review')
        ->and($row->data['place_id'])->toBe($place->id)
        ->and($row->body_ar)->toContain('تطبيق كالم')   // names the Calm app for new hosts
        ->and($row->body_en)->toContain('Calm app')
        ->and($sms->calls)->toHaveCount(1)
        ->and($sms->calls[0]['phone'])->toBe('513000030');
});

it('notifies the host when they get a booking', function (): void {
    $sms = fakeSms();
    fakePush();
    $host = User::factory()->create(['phone' => '513000031']);
    $guest = User::factory()->create(['phone' => '513000032']);
    $place = Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Hillside', 'price' => 500, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 2, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
    $booking = newBooking($place, $guest);

    $this->service->hostNewBooking($booking);

    $row = UserNotification::query()->where('user_id', $host->id)->sole();
    expect($row->type)->toBe('host_new_booking')
        ->and($row->data['booking_id'])->toBe($booking->id)
        ->and($row->data['place_id'])->toBe($place->id)
        ->and($row->body_ar)->toContain($booking->reference)   // booking reference in the message
        ->and($sms->calls)->toHaveCount(1)
        ->and($sms->calls[0]['phone'])->toBe('513000031');
});

it('notifies the guest when their booking is confirmed', function (): void {
    $sms = fakeSms();
    fakePush();
    $host = User::factory()->create(['phone' => '513000033']);
    $guest = User::factory()->create(['phone' => '513000034']);
    $place = Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Garden House', 'price' => 500, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 2, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
    $booking = newBooking($place, $guest);
    $booking->update(['check_in_time' => '15:00', 'check_out_time' => '12:00']);

    $this->service->bookingConfirmed($booking);

    $row = UserNotification::query()->where('user_id', $guest->id)->sole();
    expect($row->type)->toBe('booking_confirmed')
        ->and($row->data['booking_id'])->toBe($booking->id)
        ->and($row->body_ar)->toContain($booking->reference)        // booking reference
        ->and($row->body_ar)->toContain($booking->start_date->translatedFormat('j')) // stay date present
        ->and($row->body_ar)->toContain('PM')                       // AM/PM time
        ->and($sms->calls)->toHaveCount(1)
        ->and($sms->calls[0]['phone'])->toBe('513000034');
});

it('puts the booking reference in cancellation messages (guest + host)', function (): void {
    fakeSms();
    fakePush();
    $host = User::factory()->create(['phone' => '513000041']);
    $guest = User::factory()->create(['phone' => '513000042']);
    $place = Place::query()->create([
        'host_user_id' => $host->id,
        'place_type_id' => PlaceType::query()->first()->id,
        'city_area_id' => CityArea::query()->first()->id,
        'title' => 'Cliff House', 'price' => 500, 'check_in_time' => '15:00', 'check_out_time' => '12:00',
        'max_guests' => 2, 'status' => PlaceStatus::Active->value, 'review_status' => PlaceReviewStatus::Approved->value,
    ]);
    $booking = newBooking($place, $guest);

    $this->service->bookingCanceledByHost($booking);

    $guestRow = UserNotification::query()->where('user_id', $guest->id)->where('type', 'booking_canceled_by_host')->sole();
    $hostRow = UserNotification::query()->where('user_id', $host->id)->where('type', 'booking_canceled_by_host')->sole();
    expect($guestRow->body_ar)->toContain($booking->reference)
        ->and($hostRow->body_ar)->toContain($booking->reference);
});
