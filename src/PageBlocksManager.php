<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks;

use DannAPI\FilamentPageBlocks\Contracts\BlockContract;
use DannAPI\FilamentPageBlocks\Data\PageTemplate;
use DannAPI\FilamentPageBlocks\Registry\BlockRegistry;
use DannAPI\FilamentPageBlocks\Registry\PageTemplateRegistry;
use DannAPI\FilamentPageBlocks\Registry\PermissionRegistry;
use DannAPI\FilamentPageBlocks\Support\RoleAccess;
use Illuminate\Support\Facades\Gate;

final readonly class PageBlocksManager
{
    public function __construct(
        private BlockRegistry $blocks,
        private PageTemplateRegistry $templates,
        private PermissionRegistry $permissions,
    ) {}

    /** @param iterable<class-string<BlockContract>> $blocks */
    public function register(iterable $blocks): self
    {
        $this->blocks->register($blocks);

        return $this;
    }

    /** @param iterable<PageTemplate> $templates */
    public function templates(iterable $templates): self
    {
        $this->templates->register($templates);

        return $this;
    }

    /** @param array<string, string> $permissions */
    public function permissions(string $group, array $permissions): self
    {
        $this->permissions->register($group, $permissions);
        foreach (array_keys($permissions) as $permission) {
            if (! Gate::has($permission)) {
                Gate::define($permission, fn ($user): bool => app(RoleAccess::class)->allows($user, $permission));
            }
        }

        return $this;
    }

    public function registry(): BlockRegistry
    {
        return $this->blocks;
    }
}
