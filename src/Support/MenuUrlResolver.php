<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Enums\MenuLinkType;
use DannAPI\FilamentPageBlocks\Models\MenuItem;

final readonly class MenuUrlResolver
{
    public function __construct(private PageUrlGenerator $pages) {}

    public function resolve(MenuItem $item): string
    {
        if ($item->link_type === MenuLinkType::Page && $item->page !== null) {
            return $this->pages->to($item->page);
        }

        if ($item->link_type === MenuLinkType::Admin) {
            return app(AdminNavigationManager::class)->url((string) $item->url) ?? '#';
        }

        $url = trim((string) $item->url);
        if ($url === '' || preg_match('~^(?:javascript|data|vbscript):~i', $url) === 1) {
            return '#';
        }

        if (preg_match('~^(?:https?:)?//|^(?:#|mailto:|tel:)~', $url) === 1) {
            return $url;
        }

        return url('/'.ltrim($url, '/'));
    }
}
