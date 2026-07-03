<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Data;

use Illuminate\Support\Str;

final readonly class FrontendAssets
{
    /**
     * @param  array<int, string>  $styles
     * @param  array<int, string>  $scripts
     */
    public function __construct(
        public array $styles,
        public array $scripts,
        public ?string $favicon,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            styles: self::strings((array) config('filament-page-blocks.frontend.assets.styles', [])),
            scripts: self::strings((array) config('filament-page-blocks.frontend.assets.scripts', [])),
            favicon: is_string($favicon = config('filament-page-blocks.frontend.assets.favicon')) ? $favicon : null,
        );
    }

    public function url(string $path): string
    {
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        $path = preg_replace('~^(?:\./)+~', '', $path) ?? $path;

        return asset(ltrim($path, '/'));
    }

    /** @param array<int|string, mixed> $values @return array<int, string> */
    private static function strings(array $values): array
    {
        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }
}
