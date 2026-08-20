<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use RuntimeException;

final class SortPositionManager
{
    /** @var array<string, true> */
    private static array $validatedColumns = [];

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int|string>  $recordKeys
     * @return array<int, int>
     */
    public function captureSlots(
        string $modelClass,
        array $recordKeys,
        string $column = 'sort',
        string $direction = 'asc',
    ): array {
        $this->ensureDirection($direction);

        $model = new $modelClass;
        $this->ensureColumn($model, $column);

        $slots = $model->newQuery()
            ->whereKey(array_values($recordKeys))
            ->pluck($column)
            ->map(static fn (mixed $position): int => (int) $position)
            ->all();

        if (count($slots) !== count($recordKeys)) {
            throw new RuntimeException('Unable to capture every visible sortable record. Refresh the table and try again.');
        }

        $direction === 'desc'
            ? rsort($slots, SORT_NUMERIC)
            : sort($slots, SORT_NUMERIC);

        return array_values($slots);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int|string>  $recordKeys
     * @param  array<int, int>  $slots
     */
    public function restoreSlots(
        string $modelClass,
        array $recordKeys,
        array $slots,
        string $column = 'sort',
    ): void {
        if ($recordKeys === [] || count($recordKeys) !== count($slots)) {
            throw new InvalidArgumentException('Sortable record keys and position slots must contain the same number of items.');
        }

        $model = new $modelClass;
        $this->ensureColumn($model, $column);

        $connection = $model->getConnection();
        $keyName = $model->getKeyName();
        $grammar = $connection->getQueryGrammar();
        $wrappedKey = $grammar->wrap($keyName);
        $wrappedColumn = $grammar->wrap($column);
        $assignments = array_combine(array_values($recordKeys), array_values($slots));

        if ($assignments === false) {
            throw new RuntimeException('Unable to map sortable records to their global position slots.');
        }

        $cases = [];
        foreach ($assignments as $recordKey => $slot) {
            $cases[] = 'when '.$wrappedKey.' = '.$connection->escape($recordKey).' then '.(int) $slot;
        }

        $model->newQuery()
            ->whereKey(array_values($recordKeys))
            ->update([
                $column => new Expression('case '.implode(' ', $cases).' else '.$wrappedColumn.' end'),
            ]);
    }

    public function prepareForCreate(Model $record, string $column = 'sort'): void
    {
        $this->ensureColumn($record, $column);

        $requested = $this->normalizeRequestedPosition($record->getAttribute($column));
        $query = $record->newQuery();

        if ($requested === null) {
            $record->setAttribute($column, ((int) $query->max($column)) + 1);

            return;
        }

        $query->where($column, '>=', $requested)->toBase()->increment($column);
        $record->setAttribute($column, $requested);
    }

    public function prepareForUpdate(Model $record, string $column = 'sort'): void
    {
        if (! $record->isDirty($column)) {
            return;
        }

        $this->ensureColumn($record, $column);

        $requested = $this->normalizeRequestedPosition($record->getAttribute($column)) ?? 1;
        $current = max(1, (int) $record->getRawOriginal($column));
        $record->setAttribute($column, $requested);

        if ($requested === $current) {
            return;
        }

        $query = $record->newQuery()->whereKeyNot($record->getKey());

        if ($requested < $current) {
            $query
                ->where($column, '>=', $requested)
                ->where($column, '<', $current)
                ->toBase()
                ->increment($column);

            return;
        }

        $query
            ->where($column, '>', $current)
            ->where($column, '<=', $requested)
            ->toBase()
            ->decrement($column);
    }

    private function normalizeRequestedPosition(mixed $position): ?int
    {
        if (! is_numeric($position)) {
            return null;
        }

        $position = (int) $position;

        return $position > 0 ? $position : null;
    }

    private function ensureColumn(Model $model, string $column): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new InvalidArgumentException('The sortable position column name is invalid.');
        }

        $cacheKey = $model->getConnectionName().'|'.$model->getTable().'|'.$column;
        if (isset(self::$validatedColumns[$cacheKey])) {
            return;
        }

        if (! $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), $column)) {
            throw new InvalidArgumentException("The sortable position column [{$column}] does not exist on [{$model->getTable()}].");
        }

        self::$validatedColumns[$cacheKey] = true;
    }

    private function ensureDirection(string $direction): void
    {
        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('The reorder direction must be [asc] or [desc].');
        }
    }
}
