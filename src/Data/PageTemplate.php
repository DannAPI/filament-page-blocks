<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Data;

final class PageTemplate
{
    private string $label;

    /** @var array<class-string|string>|string */
    private array|string $blocks = '*';

    private string $layout = 'filament-page-blocks::pages.default';

    private function __construct(public readonly string $identifier)
    {
        $this->label = $identifier;
    }

    public static function make(string $identifier): self
    {
        return new self($identifier);
    }

    /** @param array<class-string|string>|string $blocks */
    public static function from(string $identifier, string $label, array|string $blocks, string $layout): self
    {
        return self::make($identifier)->label($label)->blocks($blocks)->layout($layout);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** @param array<class-string|string>|string $blocks */
    public function blocks(array|string $blocks): self
    {
        $this->blocks = $blocks;

        return $this;
    }

    public function allBlocks(): self
    {
        $this->blocks = '*';

        return $this;
    }

    public function layout(string $layout): self
    {
        $this->layout = $layout;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /** @return array<class-string|string>|string */
    public function getBlocks(): array|string
    {
        return $this->blocks;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function allows(string $identifier, ?string $class = null): bool
    {
        return $this->blocks === '*' || in_array($identifier, $this->blocks, true) || ($class !== null && in_array($class, $this->blocks, true));
    }
}
