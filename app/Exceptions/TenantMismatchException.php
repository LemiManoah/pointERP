<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class TenantMismatchException extends RuntimeException
{
    public static function forCreation(): self
    {
        return new self('The record tenant does not match the current tenant context.');
    }

    public static function immutable(): self
    {
        return new self('The tenant for an existing record cannot be changed.');
    }
}
