<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Contracts;

use DannAPI\FilamentPageBlocks\Models\Page;
use Filament\Schemas\Components\Component;

interface BlockContract
{
    public static function getName(): string;

    public static function getLabel(): string;

    public static function getIcon(): string;

    /** @return array<Component> */
    public static function form(): array;

    public static function view(): string;

    /** @return array<string, mixed> */
    public static function defaults(): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function normalize(array $data): array;

    /** @param array<string, mixed> $data */
    public static function summary(array $data): string;

    public static function authorize(?Page $page = null): bool;

    public static function isReusable(): bool;
}
