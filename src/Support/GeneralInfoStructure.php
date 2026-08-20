<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Illuminate\Database\Eloquent\Model;

final class GeneralInfoStructure
{
    /**
     * Keep the developer-defined keys and allow the administrator to change values only.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    public function preserve(Model $record, array $submitted): array
    {
        $submitted['data'] = $this->preserveValues(
            (array) $record->getAttribute('data'),
            (array) ($submitted['data'] ?? []),
        );
        $submitted['rich_text'] = $this->preserveNamedEntries(
            (array) $record->getAttribute('rich_text'),
            (array) ($submitted['rich_text'] ?? []),
            ['content'],
        );
        $submitted['images'] = $this->preserveNamedEntries(
            (array) $record->getAttribute('images'),
            (array) ($submitted['images'] ?? []),
            ['path'],
        );

        return $submitted;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    private function preserveValues(array $existing, array $submitted): array
    {
        $values = [];

        foreach ($existing as $key => $value) {
            $values[$key] = array_key_exists($key, $submitted)
                ? $submitted[$key]
                : $value;
        }

        return $values;
    }

    /**
     * @param  array<int|string, mixed>  $existing
     * @param  array<int|string, mixed>  $submitted
     * @param  array<int, string>  $editableFields
     * @return array<int, array<string, mixed>>
     */
    private function preserveNamedEntries(array $existing, array $submitted, array $editableFields): array
    {
        $submittedByKey = collect($submitted)
            ->filter(static fn (mixed $entry): bool => is_array($entry) && is_string($entry['key'] ?? null))
            ->keyBy(static fn (array $entry): string => $entry['key']);

        return collect($existing)
            ->filter(static fn (mixed $entry): bool => is_array($entry) && is_string($entry['key'] ?? null))
            ->map(static function (array $entry) use ($submittedByKey, $editableFields): array {
                $submittedEntry = $submittedByKey->get($entry['key']);
                if (! is_array($submittedEntry)) {
                    return $entry;
                }

                foreach ($editableFields as $field) {
                    if (array_key_exists($field, $submittedEntry)) {
                        $entry[$field] = $submittedEntry[$field];
                    }
                }

                return $entry;
            })
            ->values()
            ->all();
    }
}
