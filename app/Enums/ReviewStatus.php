<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Moderation state of a guest's place review. Distinct from PlaceReviewStatus,
 * which governs PLACE submission approval — not guest reviews.
 */
enum ReviewStatus: string
{
    /** Freshly submitted, awaiting admin moderation. Not shown publicly. */
    case UnderReview = 'under_review';

    /** Approved — visible on the place page, host, and counted in the rating. */
    case Published = 'published';

    /** Hidden by admin. Locked: the guest can't delete it or re-review the place. */
    case Blocked = 'blocked';
}
