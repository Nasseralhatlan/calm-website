<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingStatus: string
{
    /** Created, holding the dates, awaiting a successful Moyasar payment. */
    case PendingPayment = 'pending_payment';

    /** Paid and confirmed — the dates are booked. */
    case Confirmed = 'confirmed';

    /** Hold lapsed (or payment failed/cancelled) without confirmation. */
    case Expired = 'expired';

    /** A confirmed booking later cancelled by the host. */
    case CanceledByHost = 'canceled_by_host';

    /** A confirmed booking later cancelled by the guest. */
    case CanceledByGuest = 'canceled_by_guest';

    /** The stay has finished. */
    case Completed = 'completed';

    /**
     * Statuses that occupy the calendar. A pending hold only counts while it
     * hasn't passed its expiry — the availability check enforces that
     * separately so dates free up even if the expiry job is lagging.
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::PendingPayment, self::Confirmed, self::Completed];
    }
}
