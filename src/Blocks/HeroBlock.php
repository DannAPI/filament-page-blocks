<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Blocks;

final class HeroBlock extends AbstractBlock
{
    public static function getName(): string
    {
        return 'hero';
    }

    public static function getLabel(): string
    {
        return 'Hero';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-photo';
    }

    public static function view(): string
    {
        return 'filament-page-blocks::blocks.hero';
    }

    public static function form(): array
    {
        return [
            self::text('title', required: true, maxLength: 160),
            self::textarea('text', maxLength: 1000, rows: 3),
            self::image('image'),
            self::text('button_text', maxLength: 80),
            self::text('button_url', maxLength: 2048, rules: ['url']),
        ];
    }

    public static function normalize(array $data): array
    {
        $data = parent::normalize($data);
        $url = trim((string) $data['button_url']);
        $isRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $data['button_url'] = ($url === '' || $isRelative || in_array($scheme, ['http', 'https'], true)) ? $url : '';

        return $data;
    }

    public static function summary(array $data): string
    {
        return trim((string) ($data['title'] ?? '')) ?: self::getLabel();
    }
}
