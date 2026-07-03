<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Contracts;

interface LegacyPayloadTransformer
{
    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function __invoke(array $payload, object $legacyPage): array;
}
