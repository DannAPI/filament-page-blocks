<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Rendering\RichTextSanitizer;
use Illuminate\Contracts\Support\Htmlable;
use Stringable;

final readonly class RichTextExcerpt
{
    public function __construct(private RichTextSanitizer $sanitizer) {}

    public function plain(mixed $state): string
    {
        if ($state instanceof Htmlable) {
            $state = $state->toHtml();
        }

        if (is_array($state)) {
            return $this->normalize(implode(' ', $this->jsonText($state)));
        }

        if ($state instanceof Stringable) {
            $state = (string) $state;
        }

        if (! is_string($state) || $state === '') {
            return '';
        }

        $html = html_entity_decode($state, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = $this->sanitizer->sanitize($html);
        $html = preg_replace('~<(?:br\s*/?|/(?:p|div|li|h[1-6]|blockquote))\s*>~i', ' ', $html) ?? $html;

        return $this->normalize(strip_tags($html));
    }

    /** @return array<int, string> */
    private function jsonText(array $value): array
    {
        $text = [];
        if (is_string($value['text'] ?? null)) {
            $text[] = $value['text'];
        }

        foreach ($value as $key => $child) {
            if ($key === 'text' || ! is_array($child)) {
                continue;
            }

            $text = [...$text, ...$this->jsonText($child)];
        }

        return $text;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $text);
    }
}
