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

    /** A confirmed booking cancelled by an admin (e.g. on the guest's request). */
    case CanceledByAdmin = 'canceled_by_admin';

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

    /** Any of the cancellation outcomes. */
    public function isCanceled(): bool
    {
        return in_array($this, [self::CanceledByHost, self::CanceledByGuest, self::CanceledByAdmin], true);
    }

    /** Bilingual label for admin/host/guest screens. */
    public function label(bool $ar): string
    {
        return match ($this) {
            self::PendingPayment => $ar ? 'بانتظار الدفع' : 'Pending payment',
            self::Confirmed => $ar ? 'مؤكد' : 'Confirmed',
            self::Expired => $ar ? 'منتهي' : 'Expired',
            self::CanceledByHost => $ar ? 'ألغاه المضيف' : 'Cancelled by host',
            self::CanceledByGuest => $ar ? 'ألغاه الضيف' : 'Cancelled by guest',
            self::CanceledByAdmin => $ar ? 'ألغته الإدارة' : 'Cancelled by admin',
            self::Completed => $ar ? 'مكتمل' : 'Completed',
        };
    }

    /** Badge palette (saturated bg + light dot) matching the admin pills. */
    public function pill(): string
    {
        return match ($this) {
            self::PendingPayment => '#f59e0b',
            self::Confirmed => '#10b981',
            self::Completed => '#3b82f6',
            self::Expired => '#9ca3af',
            self::CanceledByHost, self::CanceledByGuest, self::CanceledByAdmin => '#ef4444',
        };
    }
}
