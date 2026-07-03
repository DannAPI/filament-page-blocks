<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Data;

use ArrayAccess;
use DannAPI\FilamentPageBlocks\Support\BlockRelationResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

/** @implements ArrayAccess<string, mixed> */
final readonly class BlockData implements ArrayAccess, JsonSerializable
{
    /** @param array<string, mixed> $values */
    public function __construct(
        private array $values,
        private ?BlockRelationResolver $relations = null,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    public function relation(string $name): Model|Collection|null
    {
        return $this->relations?->resolve($name);
    }

    public function model(string $name): ?Model
    {
        return $this->relations?->resolveOne($name);
    }

    /** @return Collection<int, Model> */
    public function models(string $name): Collection
    {
        return $this->relations?->resolveMany($name) ?? new Collection;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[(string) $offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new \LogicException('BlockData is immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new \LogicException('BlockData is immutable.');
    }

    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
