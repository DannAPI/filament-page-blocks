<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Data\BlockRelationDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class BlockRelationResolver
{
    /** @var array<string, Model|Collection<int, Model>|null> */
    private array $resolved = [];

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, BlockRelationDefinition>  $definitions
     */
    public function __construct(
        private readonly array $values,
        private readonly array $definitions,
    ) {}

    public function resolve(string $name): Model|Collection|null
    {
        if (array_key_exists($name, $this->resolved)) {
            return $this->resolved[$name];
        }

        $definition = $this->definitions[$name] ?? null;
        if ($definition === null) {
            return $this->resolved[$name] = null;
        }

        return $this->resolved[$name] = $definition->isMorphTo()
            ? $this->resolveMorphTo($definition)
            : $this->resolveModel($definition);
    }

    /** @return Collection<int, Model> */
    public function resolveMany(string $name): Collection
    {
        $resolved = $this->resolve($name);

        if ($resolved instanceof Collection) {
            return $resolved;
        }

        return new Collection($resolved instanceof Model ? [$resolved] : []);
    }

    public function resolveOne(string $name): ?Model
    {
        $resolved = $this->resolve($name);

        return $resolved instanceof Model ? $resolved : $resolved?->first();
    }

    private function resolveModel(BlockRelationDefinition $definition): Model|Collection|null
    {
        if ($definition->model === null) {
            return $definition->multiple ? new Collection : null;
        }

        $value = $this->values[$definition->name] ?? null;
        $query = $this->query($definition->model, $definition->modifyQueryUsing);

        if (! $definition->multiple) {
            return filled($value) ? $query->where($definition->keyAttribute, $value)->first() : null;
        }

        $keys = array_values(array_filter(is_array($value) ? $value : [], static fn (mixed $key): bool => filled($key)));
        if ($keys === []) {
            return new Collection;
        }

        $positions = array_flip(array_map('strval', $keys));

        return $query->whereIn($definition->keyAttribute, $keys)->get()
            ->sortBy(static fn (Model $model): int => $positions[(string) $model->getAttribute($definition->keyAttribute)] ?? PHP_INT_MAX)
            ->values();
    }

    private function resolveMorphTo(BlockRelationDefinition $definition): ?Model
    {
        $type = $this->values[(string) $definition->morphTypeField] ?? null;
        $key = $this->values[(string) $definition->morphIdField] ?? null;
        if (! is_string($type) || ! isset($definition->morphTypes[$type]) || ! filled($key)) {
            return null;
        }

        $model = new $type;

        return $this->query($type, $definition->modifyQueryUsing)
            ->where($model->getKeyName(), $key)
            ->first();
    }

    /** @param class-string<Model> $model */
    private function query(string $model, ?\Closure $modifyQueryUsing): Builder
    {
        $query = $model::query();
        if ($modifyQueryUsing !== null) {
            $modified = $modifyQueryUsing($query);
            if ($modified instanceof Builder) {
                $query = $modified;
            }
        }

        return $query;
    }
}
