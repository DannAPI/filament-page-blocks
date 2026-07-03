<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Registry;

use DannAPI\FilamentPageBlocks\Data\PageTemplate;
use InvalidArgumentException;

final class PageTemplateRegistry
{
    /** @var array<string, PageTemplate> */
    private array $templates = [];

    /** @param iterable<PageTemplate> $templates */
    public function register(iterable $templates): self
    {
        foreach ($templates as $template) {
            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $template->identifier) !== 1) {
                throw new InvalidArgumentException("Invalid page template identifier [{$template->identifier}].");
            }
            if (isset($this->templates[$template->identifier]) && $this->templates[$template->identifier] !== $template) {
                throw new InvalidArgumentException("Page template [{$template->identifier}] is already registered.");
            }
            $this->templates[$template->identifier] = $template;
        }

        return $this;
    }

    public function get(?string $identifier = null): PageTemplate
    {
        $identifier ??= (string) config('filament-page-blocks.default_template', 'default');

        return $this->templates[$identifier] ?? throw new InvalidArgumentException("Unknown page template [{$identifier}].");
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return array_map(static fn (PageTemplate $template): string => $template->getLabel(), $this->templates);
    }

    public function hasOnlyDefault(): bool
    {
        return array_keys($this->templates) === ['default'];
    }
}
