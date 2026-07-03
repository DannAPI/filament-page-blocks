<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Models;

use DannAPI\FilamentPageBlocks\Rendering\RichTextSanitizer;
use DannAPI\FilamentPageBlocks\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use LogicException;

class GeneralInfo extends Model
{
    protected $fillable = ['data', 'images', 'rich_text'];

    protected static function booted(): void
    {
        static::creating(static function (GeneralInfo $generalInfo): void {
            if (static::query()->exists()) {
                throw new LogicException('Only one GeneralInfo record may exist.');
            }

            $generalInfo->singleton_key = 1;
        });

        static::saving(static function (GeneralInfo $generalInfo): void {
            $generalInfo->singleton_key = 1;
            $generalInfo->rich_text = collect($generalInfo->rich_text ?? [])
                ->filter(static fn (mixed $item): bool => is_array($item) && is_string($item['key'] ?? null))
                ->map(static fn (array $item): array => [
                    'key' => $item['key'],
                    'content' => app(RichTextSanitizer::class)->sanitize((string) ($item['content'] ?? '')),
                ])
                ->values()
                ->all();
        });
    }

    public function getTable(): string
    {
        return (string) config('filament-page-blocks.tables.general_info', 'general_info');
    }

    protected function casts(): array
    {
        return ['data' => 'array', 'images' => 'array', 'rich_text' => 'array', 'singleton_key' => 'integer'];
    }

    public static function singleton(): ?static
    {
        return static::query()->first();
    }

    /** @param array<string, mixed> $attributes */
    public static function singletonOrCreate(array $attributes = []): static
    {
        /** @var static $generalInfo */
        $generalInfo = static::query()->firstOrCreate(
            ['singleton_key' => 1],
            $attributes,
        );

        return $generalInfo;
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return data_get($this->data ?? [], $key, $default);
    }

    public function image(string $key): ?string
    {
        $images = $this->images ?? [];
        if (is_string($images[$key] ?? null)) {
            return $images[$key];
        }

        foreach ($images as $image) {
            if (! is_array($image) || ($image['key'] ?? null) !== $key) {
                continue;
            }

            return is_string($image['path'] ?? null) ? $image['path'] : null;
        }

        return null;
    }

    public function imageUrl(string $key): ?string
    {
        return Media::url($this->image($key));
    }

    public function richText(string $key): HtmlString
    {
        foreach ($this->rich_text ?? [] as $item) {
            if (! is_array($item) || ($item['key'] ?? null) !== $key) {
                continue;
            }

            return new HtmlString(
                app(RichTextSanitizer::class)->sanitize((string) ($item['content'] ?? '')),
            );
        }

        return new HtmlString;
    }
}
