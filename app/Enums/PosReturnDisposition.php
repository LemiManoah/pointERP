<?php

declare(strict_types=1);

namespace App\Enums;

enum PosReturnDisposition: string
{
    case Restock = 'restock';
    case Damaged = 'damaged';
}
