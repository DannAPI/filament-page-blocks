<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Registry;

use InvalidArgumentException;

final class PermissionRegistry
{
    /** @var array<string, array<string, string>> */
    private array $groups = [];

    /** @param array<string, string> $permissions */
    public function register(string $group, array $permissions): self
    {
        if ($group === '') {
            throw new InvalidArgumentException('Permission group cannot be empty.');
        }

        foreach ($permissions as $permission => $label) {
            if (! is_string($permission) || $permission === '' || ! is_string($label)) {
                throw new InvalidArgumentException('Permissions must be non-empty string keys and string labels.');
            }
            $this->groups[$group][$permission] = $label;
        }

        return $this;
    }

    /** @return array<string, array<string, string>> */
    public function groups(): array
    {
        return $this->groups;
    }

    /** @return array<string, string> */
    public function options(): array
    {
        $options = [];
        foreach ($this->groups as $group => $permissions) {
            foreach ($permissions as $permission => $label) {
                $options[$permission] = $group.' — '.$label;
            }
        }

        return $options;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->options());
    }
}
