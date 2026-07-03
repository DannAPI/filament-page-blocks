<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Blocks;

use DannAPI\FilamentPageBlocks\Rendering\RichTextSanitizer;

final class RichTextBlock extends AbstractBlock
{
    public static function getName(): string
    {
        return 'rich_text';
    }

    public static function getLabel(): string
    {
        return 'Rich text';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public static function view(): string
    {
        return 'filament-page-blocks::blocks.rich-text';
    }

    public static function form(): array
    {
        return [self::text('heading', maxLength: 160), self::richText('content', required: true)];
    }

    public static function normalize(array $data): array
    {
        $data = parent::normalize($data);
        $data['content'] = app(RichTextSanitizer::class)->sanitize((string) $data['content']);

        return $data;
    }

    public static function summary(array $data): string
    {
        return trim((string) ($data['heading'] ?? '')) ?: self::getLabel();
    }
}
