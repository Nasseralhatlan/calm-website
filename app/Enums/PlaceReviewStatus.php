<?php

declare(strict_types=1);

namespace App\Enums;

enum PlaceReviewStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
