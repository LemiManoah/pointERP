<?php

declare(strict_types=1);

namespace App\Enums;

enum DsrMaterialReconciliationType: string
{
    case RequisitionIssue = 'requisition_issue';
    case DirectIssue = 'direct_issue';
    case ExternalNonStock = 'external_non_stock';
    case Return = 'return';
    case Correction = 'correction';
}
