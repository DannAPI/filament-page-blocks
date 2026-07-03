<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

final readonly class PermissionDiscovery
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  array<int, string>  $paths
     * @return array<string, array<string, string>>
     */
    public function discover(array $paths): array
    {
        $groups = [];

        foreach ($paths as $path) {
            if (! is_string($path) || ! $this->files->isDirectory($path)) {
                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $definition = require $file->getPathname();
                if (! is_array($definition)) {
                    throw new InvalidArgumentException("Permission definition [{$file->getPathname()}] must return an array.");
                }

                $group = $definition['group'] ?? null;
                $permissions = $definition['permissions'] ?? null;
                if (! is_string($group) || $group === '' || ! is_array($permissions)) {
                    throw new InvalidArgumentException("Permission definition [{$file->getPathname()}] requires group and permissions keys.");
                }

                $groups[$group] = [...($groups[$group] ?? []), ...$permissions];
            }
        }

        return $groups;
    }
}
