<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Exceptions;

use RuntimeException;

final class SystemPageMutationException extends RuntimeException
{
    public static function deletion(): self
    {
        return new self('System-managed pages cannot be deleted.');
    }
}
