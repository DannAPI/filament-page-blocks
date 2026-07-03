<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Blocks;

final class ImageTextBlock extends AbstractBlock
{
    public static function getName(): string
    {
        return 'image_text';
    }

    public static function getLabel(): string
    {
        return 'Image with text';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-rectangle-group';
    }

    public static function view(): string
    {
        return 'filament-page-blocks::blocks.image-text';
    }

    public static function form(): array
    {
        return [
            self::text('heading', required: true, maxLength: 160),
            self::textarea('text', required: true, maxLength: 3000, rows: 5),
            self::image('image', required: true),
            self::select('image_position', ['left' => 'Left', 'right' => 'Right'], default: 'left', required: true),
        ];
    }

    public static function normalize(array $data): array
    {
        $data = parent::normalize($data);
        $data['image_position'] = in_array($data['image_position'], ['left', 'right'], true) ? $data['image_position'] : 'left';

        return $data;
    }

    public static function summary(array $data): string
    {
        return trim((string) ($data['heading'] ?? '')) ?: self::getLabel();
    }
}
