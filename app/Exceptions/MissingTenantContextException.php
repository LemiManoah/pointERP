<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class MissingTenantContextException extends RuntimeException
{
    public static function unresolved(): self
    {
        return new self('A tenant context is required for this operation.');
    }

    public static function inactive(): self
    {
        return new self('The resolved tenant is not active.');
    }
}
