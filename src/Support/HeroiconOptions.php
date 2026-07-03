<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;

use function Filament\Support\generate_icon_html;

final class HeroiconOptions
{
    /** @var array<string, string>|null */
    private ?array $icons = null;

    /** @return array<string, string> */
    public function search(?string $search = null, ?int $limit = null): array
    {
        $limit ??= max(12, min(100, (int) config('filament-page-blocks.menus.admin.icons.result_limit', 48)));

        return $this->page($search, 1, $limit);
    }

    /** @return array<string, string> */
    public function page(?string $search = null, int $page = 1, ?int $perPage = null): array
    {
        $perPage ??= max(12, min(100, (int) config('filament-page-blocks.menus.admin.icons.result_limit', 48)));
        $page = max(1, $page);
        $icons = array_slice($this->filtered($search), ($page - 1) * $perPage, $perPage, true);
        $results = [];
        foreach ($icons as $name => $label) {
            $results[$name] = $this->html($name, $label);
        }

        return $results;
    }

    /** @return array<string, string> */
    public function pageLabels(?string $search = null, int $page = 1, ?int $perPage = null): array
    {
        $perPage ??= max(12, min(100, (int) config('filament-page-blocks.menus.admin.icons.result_limit', 48)));

        return array_slice(
            $this->filtered($search),
            (max(1, $page) - 1) * $perPage,
            $perPage,
            true,
        );
    }

    /** @return array<int, string> */
    public function pageOptions(?string $search = null, ?int $perPage = null): array
    {
        $perPage ??= max(12, min(100, (int) config('filament-page-blocks.menus.admin.icons.result_limit', 48)));
        $pages = max(1, (int) ceil(count($this->filtered($search)) / $perPage));
        $options = [];
        for ($page = 1; $page <= $pages; $page++) {
            $options[$page] = "Page {$page} of {$pages}";
        }

        return $options;
    }

    public function label(mixed $name): ?string
    {
        if (! is_string($name) || ! isset($this->icons()[$name])) {
            return null;
        }

        return $this->html($name, $this->icons()[$name]);
    }

    public function contains(mixed $name): bool
    {
        return is_string($name) && isset($this->icons()[$name]);
    }

    public function cssMask(mixed $name): ?string
    {
        if (! $this->contains($name)) {
            return null;
        }

        $icon = generate_icon_html((string) $name);
        if ($icon === null) {
            return null;
        }

        return 'url(data:image/svg+xml;base64,'.base64_encode($icon->toHtml()).')';
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_keys($this->icons());
    }

    /** @return array<string, string> */
    private function icons(): array
    {
        if ($this->icons !== null) {
            return $this->icons;
        }

        $icons = [];
        foreach (Heroicon::cases() as $icon) {
            if (! str_starts_with($icon->name, 'Outlined')) {
                continue;
            }

            $name = 'heroicon-'.$icon->value;
            $icons[$name] = Str::headline(Str::after($icon->name, 'Outlined'));
        }

        asort($icons);

        return $this->icons = $icons;
    }

    /** @return array<string, string> */
    private function filtered(?string $search): array
    {
        $needle = Str::lower(trim((string) $search));
        if ($needle === '') {
            return $this->icons();
        }

        return array_filter(
            $this->icons(),
            static fn (string $label, string $name): bool => Str::contains(Str::lower($name.' '.$label), $needle),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function html(string $name, string $label): string
    {
        $icon = generate_icon_html(
            $name,
            attributes: (new ComponentAttributeBag)->class(['fi-fpb-icon-option-svg']),
        );

        return '<span class="fi-fpb-icon-option">'
            .$icon?->toHtml()
            .'<span>'.e($label).'</span>'
            .'<code>'.e($name).'</code>'
            .'</span>';
    }
}
