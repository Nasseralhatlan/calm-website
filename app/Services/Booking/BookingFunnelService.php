<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\Place;
use App\Models\User;

/**
 * The web booking funnel (/book/{place}) — the link hosts share so customers
 * book on the web with the same rails as the app. Composes the funnel-only
 * concerns (fresh OTP accounts have no name yet; the payer must come back to
 * the web status page) around the ONE core booking flow.
 */
final class BookingFunnelService
{
    public function __construct(private readonly BookingService $bookings) {}

    /**
     * @param  array{check_in: string, check_out: string, guests: int, name?: ?string}  $data
     */
    public function book(User $guest, Place $place, array $data): Booking
    {
        // First-time web customers register mid-funnel via OTP — their shell
        // account has no name yet; the funnel form collects it.
        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '' && trim((string) $guest->name) === '') {
            $guest->update(['name' => $name]);
        }

        return $this->bookings->create(
            $guest,
            $place,
            $data['check_in'],
            $data['check_out'],
            (int) $data['guests'],
            ['return_url' => fn (Booking $booking): string => route('book.status', $booking)],
        );
    }
}
