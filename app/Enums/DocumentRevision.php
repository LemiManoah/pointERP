<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentRevision: string
{
    case Initial = 'initial';
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case E = 'E';
    case F = 'F';
    case R0 = '0';
    case R1 = '1';
    case R2 = '2';
    case R3 = '3';
    case R4 = '4';
    case R5 = '5';
    case R6 = '6';
    case R7 = '7';
    case R8 = '8';
    case R9 = '9';
    case R10 = '10';

    public function label(): string
    {
        return $this === self::Initial ? 'Initial issue' : 'Revision '.$this->value;
    }
}
