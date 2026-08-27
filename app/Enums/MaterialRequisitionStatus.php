<?php

declare(strict_types=1);

namespace App\Enums;

enum MaterialRequisitionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';
    case PartiallyIssued = 'partially_issued';
    case Fulfilled = 'fulfilled';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
