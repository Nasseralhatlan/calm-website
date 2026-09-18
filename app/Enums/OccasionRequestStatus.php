<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an occasion lead sits in the events team's pipeline. The guest is
 * promised they can follow this, so every value has to mean something to them
 * as well as to staff.
 */
enum OccasionRequestStatus: string
{
    /** Just submitted; nobody has called yet. */
    case New = 'new';

    /** The team has picked it up and is working out what the guest needs. */
    case UnderProcessing = 'under_processing';

    /** Agreed — we're actively arranging the occasion. */
    case Ongoing = 'ongoing';

    /** Done. */
    case Completed = 'completed';

    /** Bilingual label for the admin table and the guest's list. */
    public function label(bool $ar): string
    {
        return match ($this) {
            self::New => $ar ? 'جديد' : 'New',
            self::UnderProcessing => $ar ? 'قيد المعالجة' : 'Under processing',
            self::Ongoing => $ar ? 'جارٍ التنفيذ' : 'Ongoing',
            self::Completed => $ar ? 'مكتمل' : 'Completed',
        };
    }

    /** Badge palette matching the admin pills (see BookingStatus::pill). */
    public function pill(): string
    {
        return match ($this) {
            self::New => '#f59e0b',
            self::UnderProcessing => '#3b82f6',
            self::Ongoing => '#8b5cf6',
            self::Completed => '#16a34a',
        };
    }

    /** Washed-out `pill()`, for the fill behind a status-change button. */
    public function tint(): string
    {
        return match ($this) {
            self::New => '#FEF4E6',
            self::UnderProcessing => '#EAF1FE',
            self::Ongoing => '#F2ECFE',
            self::Completed => '#E8F6EC',
        };
    }
}
