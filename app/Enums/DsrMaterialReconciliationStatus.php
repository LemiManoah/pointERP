<?php

declare(strict_types=1);

namespace App\Enums;

enum DsrMaterialReconciliationStatus: string
{
    case NotLinked = 'not_linked';
    case Pending = 'pending';
    case Partial = 'partial';
    case Reconciled = 'reconciled';
    case External = 'external';
    case Exception = 'exception';
}
