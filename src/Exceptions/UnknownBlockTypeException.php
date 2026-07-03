<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Exceptions;

use RuntimeException;

final class UnknownBlockTypeException extends RuntimeException
{
    public static function for(string $type): self
    {
        return new self("Unknown page block type [{$type}].");
    }
}
