<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A booking + its payment handles (guest-facing). After creation the client
 * opens `payment.url` (Moyasar hosted page) in a WebView; `pricing.total_minor`
 * is what's being charged (halalas). Poll the payment-status endpoint, or rely
 * on the webhook, to learn when `status` flips to `confirmed`.
 *
 * Calm's commission is host-side and intentionally not exposed here.
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'place_id' => $this->place_id,
            // Compact place summary — present when the place is eager-loaded
            // (the bookings list loads it; the create/payment responses don't).
            'place' => $this->whenLoaded('place', function () {
                $place = $this->place;
                $city = $place?->cityArea?->city;

                // The exact map link is sensitive: only reveal it once the
                // booking is a real, paid stay (confirmed or completed).
                $locationUnlocked = in_array(
                    $this->booking_status,
                    [BookingStatus::Confirmed, BookingStatus::Completed],
                    true,
                );

                return $place ? [
                    'id' => $place->id,
                    // Canonical title + the bilingual pair so the app can show
                    // the place name in the viewer's language (with fallback).
                    'title' => $place->title,
                    'title_ar' => $place->title_ar,
                    'title_en' => $place->title_en,
                    'cover_photo_url' => $place->coverPhoto?->url,
                    'location_url' => $locationUnlocked ? $place->location_url : null,
                    'type' => $place->type ? [
                        'name_en' => $place->type->name_en,
                        'name_ar' => $place->type->name_ar,
                        'icon' => $place->type->icon,
                    ] : null,
                    'city' => $city ? ['name_en' => $city->name_en, 'name_ar' => $city->name_ar] : null,
                    'city_area' => $place->cityArea ? [
                        'name_en' => $place->cityArea->name_en,
                        'name_ar' => $place->cityArea->name_ar,
                    ] : null,
                ] : null;
            }),
            // Who booked — present when eager-loaded (host's bookings list loads
            // it so the host can see/contact the guest; the guest's own list doesn't).
            'guest' => $this->whenLoaded('guest', fn () => [
                'id' => $this->guest?->id,
                'name' => $this->guest?->name,
                'phone' => $this->guest?->phone,
            ]),
            'status' => $this->booking_status->value,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'checkout_next_day' => (bool) $this->checkout_next_day,
            // Resolved checkout datetime — check-in is start_date @ check_in_time;
            // checkout is end_date (+1 day when checkout_next_day) @ check_out_time.
            'checkout_at' => $this->checkoutAt()?->toIso8601String(),
            'guests' => $this->guests,
            'currency' => 'SAR',
            'pricing' => [
                'subtotal' => $this->booking_amount / 100,
                'vat_percentage' => $this->vat_rate,
                'vat' => $this->vat_amount / 100,
                'total' => $this->total / 100,
                'total_minor' => $this->total,
            ],
            'payment' => [
                'id' => $this->payment_id,
                'method' => $this->payment_method,
                'status' => $this->payment_status,
                'url' => $this->payment_url,
            ],
            'expires_at' => $this->expires_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // The guest's review for this booking's place (place-scoped, attached
            // by BookingService::forGuestPaginated). Present only when loaded.
            'review' => $this->whenLoaded('review', fn () => $this->review ? [
                'id' => $this->review->id,
                'rate' => (int) $this->review->rate,
                'comment' => $this->review->comment,
                'status' => $this->review->status?->value,
                'created_at' => $this->review->created_at?->toIso8601String(),
            ] : null),
            // True when the stay is completed and the place hasn't been reviewed yet.
            'can_review' => $this->when(
                $this->relationLoaded('review'),
                fn (): bool => $this->booking_status === BookingStatus::Completed && $this->review === null,
            ),
        ];
    }
}
