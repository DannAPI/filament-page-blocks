<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Closure;
use DannAPI\FilamentPageBlocks\Data\BlockRelationDefinition;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class BlockFields
{
    /** @param array<int, mixed>|string $rules */
    public function text(
        string $name,
        mixed $default = '',
        bool $required = false,
        ?int $maxLength = null,
        ?string $label = null,
        array|string $rules = [],
    ): TextInput {
        $field = TextInput::make($name)->string();
        if ($maxLength !== null) {
            $field->maxLength($maxLength);
        }

        return $this->configure($field, $default, $required, $label, $rules);
    }

    /** @param array<int, mixed>|string $rules */
    public function textarea(
        string $name,
        mixed $default = '',
        bool $required = false,
        ?int $maxLength = null,
        ?int $rows = null,
        ?string $label = null,
        array|string $rules = [],
    ): Textarea {
        $field = Textarea::make($name)->string();
        if ($maxLength !== null) {
            $field->maxLength($maxLength);
        }
        if ($rows !== null) {
            $field->rows($rows);
        }

        return $this->configure($field, $default, $required, $label, $rules);
    }

    /** @param array<int|string, string>|Closure $options */
    public function select(
        string $name,
        array|Closure $options = [],
        mixed $default = null,
        bool $required = false,
        bool $multiple = false,
        ?string $label = null,
    ): Select {
        $field = Select::make($name)
            ->options($options)
            ->multiple($multiple)
            ->selectablePlaceholder(! $required);

        return $this->configure($field, $multiple && $default === null ? [] : $default, $required, $label);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $searchColumns
     */
    public function relationship(
        string $name,
        string $model,
        string $titleAttribute = 'name',
        bool $multiple = false,
        mixed $default = null,
        bool $required = false,
        ?string $label = null,
        ?string $keyAttribute = null,
        ?Closure $modifyQueryUsing = null,
        array $searchColumns = [],
        bool $preload = false,
        int $optionsLimit = 50,
    ): Select {
        $instance = $this->modelInstance($model);
        $keyAttribute ??= $instance->getKeyName();
        $searchColumns = $searchColumns === [] ? [$titleAttribute] : $searchColumns;

        $field = Select::make($name)
            ->multiple($multiple)
            ->selectablePlaceholder(! $required)
            ->searchable($searchColumns)
            ->preload($preload)
            ->optionsLimit($optionsLimit)
            ->getSearchResultsUsing(fn (?string $search): array => $this->relationOptions(
                model: $model,
                titleAttribute: $titleAttribute,
                keyAttribute: $keyAttribute,
                modifyQueryUsing: $modifyQueryUsing,
                searchColumns: $searchColumns,
                search: $search,
                limit: $optionsLimit,
            ))
            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->relationLabel(
                model: $model,
                titleAttribute: $titleAttribute,
                keyAttribute: $keyAttribute,
                value: $value,
                modifyQueryUsing: $modifyQueryUsing,
            ))
            ->getOptionLabelsUsing(fn (array $values): array => $this->relationLabels(
                model: $model,
                titleAttribute: $titleAttribute,
                keyAttribute: $keyAttribute,
                values: $values,
                modifyQueryUsing: $modifyQueryUsing,
            ))
            ->meta('filament-page-blocks.relation', new BlockRelationDefinition(
                name: $name,
                model: $model,
                keyAttribute: $keyAttribute,
                multiple: $multiple,
                modifyQueryUsing: $modifyQueryUsing,
            ));

        if ($preload) {
            $field->options(fn (): array => $this->relationOptions(
                model: $model,
                titleAttribute: $titleAttribute,
                keyAttribute: $keyAttribute,
                modifyQueryUsing: $modifyQueryUsing,
                searchColumns: $searchColumns,
                limit: $optionsLimit,
            ));
        }

        return $this->configure($field, $multiple && $default === null ? [] : $default, $required, $label);
    }

    /**
     * @param  array<class-string<Model>, string|array{title: string, label?: string}>  $types
     * @return array{Select, Select}
     */
    public function morphTo(
        string $name,
        array $types,
        mixed $defaultType = null,
        mixed $defaultId = null,
        bool $required = false,
        ?string $label = null,
        ?Closure $modifyQueryUsing = null,
        bool $preload = false,
        int $optionsLimit = 50,
    ): array {
        $normalized = [];
        $typeOptions = [];
        foreach ($types as $model => $definition) {
            $this->modelInstance($model);
            $title = is_array($definition) ? $definition['title'] : $definition;
            if (! is_string($title) || $title === '') {
                throw new InvalidArgumentException("Morph model [{$model}] requires a non-empty title attribute.");
            }
            $typeLabel = is_array($definition) && isset($definition['label'])
                ? $definition['label']
                : Str::headline(class_basename($model));
            $normalized[$model] = $title;
            $typeOptions[$model] = $typeLabel;
        }
        if ($normalized === []) {
            throw new InvalidArgumentException('A morphTo field requires at least one model type.');
        }

        $typeField = "{$name}_type";
        $idField = "{$name}_id";
        $typeSelect = $this->configure(
            Select::make($typeField)
                ->options($typeOptions)
                ->selectablePlaceholder(! $required)
                ->live()
                ->afterStateUpdated(static fn (Set $set): mixed => $set($idField, null)),
            $defaultType,
            $required,
            $label === null ? null : "{$label} type",
        );

        $idSelect = Select::make($idField)
            ->searchable()
            ->selectablePlaceholder(! $required)
            ->preload($preload)
            ->optionsLimit($optionsLimit)
            ->required(static fn (Get $get): bool => $required && filled($get($typeField)))
            ->getSearchResultsUsing(fn (Get $get, ?string $search): array => $this->morphOptions(
                types: $normalized,
                selectedType: $get($typeField),
                modifyQueryUsing: $modifyQueryUsing,
                search: $search,
                limit: $optionsLimit,
            ))
            ->getOptionLabelUsing(fn (Get $get, mixed $value): ?string => $this->morphLabel(
                types: $normalized,
                selectedType: $get($typeField),
                value: $value,
                modifyQueryUsing: $modifyQueryUsing,
            ))
            ->meta('filament-page-blocks.relation', new BlockRelationDefinition(
                name: $name,
                model: null,
                keyAttribute: 'id',
                multiple: false,
                modifyQueryUsing: $modifyQueryUsing,
                morphTypes: $normalized,
                morphTypeField: $typeField,
                morphIdField: $idField,
            ));

        if ($preload) {
            $idSelect->options(fn (Get $get): array => $this->morphOptions(
                types: $normalized,
                selectedType: $get($typeField),
                modifyQueryUsing: $modifyQueryUsing,
                limit: $optionsLimit,
            ));
        }

        $idSelect = $this->configure($idSelect, $defaultId, label: $label);
        $idSelect->required(static fn (Get $get): bool => $required && filled($get($typeField)));

        return [$typeSelect, $idSelect];
    }

    /** @param array<int|string, string>|Closure $options */
    public function radio(string $name, array|Closure $options, mixed $default = null, bool $required = false, ?string $label = null): Radio
    {
        return $this->configure(Radio::make($name)->options($options), $default, $required, $label);
    }

    /** @param array<int|string, string>|Closure $options */
    public function toggleButtons(string $name, array|Closure $options, mixed $default = null, bool $required = false, ?string $label = null): ToggleButtons
    {
        return $this->configure(ToggleButtons::make($name)->options($options), $default, $required, $label);
    }

    /** @param array<int|string, string>|Closure $options */
    public function checkboxList(string $name, array|Closure $options, array $default = [], bool $required = false, ?string $label = null): CheckboxList
    {
        return $this->configure(CheckboxList::make($name)->options($options), $default, $required, $label);
    }

    public function checkbox(string $name, bool $default = false, bool $required = false, ?string $label = null): Checkbox
    {
        return $this->configure(Checkbox::make($name), $default, $required, $label);
    }

    public function toggle(string $name, bool $default = false, bool $required = false, ?string $label = null): Toggle
    {
        return $this->configure(Toggle::make($name), $default, $required, $label);
    }

    public function richText(string $name, mixed $default = '', bool $required = false, ?string $label = null): RichEditor
    {
        $field = RichEditor::make($name);
        if (config('filament-page-blocks.fields.rich_text.resizable', true)) {
            $minHeight = (string) config('filament-page-blocks.fields.rich_text.min_height', '12rem');
            if (preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|vh)$/', $minHeight) !== 1) {
                $minHeight = '12rem';
            }

            $direction = (string) config('filament-page-blocks.fields.rich_text.resize_direction', 'vertical');
            if (! in_array($direction, ['vertical', 'both'], true)) {
                $direction = 'vertical';
            }

            $field->extraInputAttributes([
                'style' => "resize: {$direction}; overflow: auto; min-height: {$minHeight};",
            ], merge: true);
        }

        return $this->configure($field, $default, $required, $label);
    }

    public function markdown(string $name, mixed $default = '', bool $required = false, ?string $label = null): MarkdownEditor
    {
        return $this->configure(MarkdownEditor::make($name), $default, $required, $label);
    }

    public function code(string $name, mixed $default = '', bool $required = false, ?string $label = null): CodeEditor
    {
        return $this->configure(CodeEditor::make($name), $default, $required, $label);
    }

    public function date(string $name, mixed $default = null, bool $required = false, ?string $label = null): DatePicker
    {
        return $this->configure(DatePicker::make($name), $default, $required, $label);
    }

    public function dateTime(string $name, mixed $default = null, bool $required = false, ?string $label = null): DateTimePicker
    {
        return $this->configure(DateTimePicker::make($name), $default, $required, $label);
    }

    public function time(string $name, mixed $default = null, bool $required = false, ?string $label = null): TimePicker
    {
        return $this->configure(TimePicker::make($name), $default, $required, $label);
    }

    public function color(string $name, mixed $default = null, bool $required = false, ?string $label = null): ColorPicker
    {
        return $this->configure(ColorPicker::make($name), $default, $required, $label);
    }

    public function tags(string $name, array $default = [], bool $required = false, ?string $label = null): TagsInput
    {
        return $this->configure(TagsInput::make($name), $default, $required, $label);
    }

    public function keyValue(string $name, array $default = [], bool $required = false, ?string $label = null): KeyValue
    {
        return $this->configure(KeyValue::make($name), $default, $required, $label);
    }

    public function slider(string $name, int|float|null $default = null, bool $required = false, ?string $label = null): Slider
    {
        return $this->configure(Slider::make($name), $default, $required, $label);
    }

    public function hidden(string $name, mixed $default = null): Hidden
    {
        return $this->configure(Hidden::make($name), $default);
    }

    /** @param array<Component>|Closure $schema */
    public function repeater(string $name, array|Closure $schema, array $default = [], bool $required = false, ?string $label = null): Repeater
    {
        return $this->configure(Repeater::make($name)->schema($schema), $default, $required, $label);
    }

    public function image(string $name, mixed $default = null, bool $required = false, ?string $label = null, ?string $directory = null): FileUpload
    {
        $field = $this->upload(
            name: $name,
            default: $default,
            required: $required,
            label: $label,
            directory: $directory,
            acceptedFileTypes: (array) config('filament-page-blocks.media.image_mime_types', []),
            maxSize: (int) config('filament-page-blocks.media.image_max_size', config('filament-page-blocks.media.max_size', 5120)),
        );

        return $field->image();
    }

    public function video(string $name, mixed $default = null, bool $required = false, ?string $label = null, ?string $directory = null): FileUpload
    {
        return $this->upload(
            name: $name,
            default: $default,
            required: $required,
            label: $label,
            directory: $directory,
            acceptedFileTypes: (array) config('filament-page-blocks.media.video_mime_types', []),
            maxSize: (int) config('filament-page-blocks.media.video_max_size', 51200),
        );
    }

    public function file(string $name, mixed $default = null, bool $required = false, bool $multiple = false, ?string $label = null, ?string $directory = null): FileUpload
    {
        return $this->upload(
            name: $name,
            default: $multiple && $default === null ? [] : $default,
            required: $required,
            label: $label,
            directory: $directory,
            acceptedFileTypes: (array) config('filament-page-blocks.media.file_mime_types', []),
            maxSize: (int) config('filament-page-blocks.media.file_max_size', 10240),
        )->multiple($multiple);
    }

    /** @template T of Field @param class-string<T> $component @return T */
    public function make(string $component, string $name): Field
    {
        return $component::make($name);
    }

    /** @param array<int, string> $acceptedFileTypes */
    private function upload(
        string $name,
        mixed $default,
        bool $required,
        ?string $label,
        ?string $directory,
        array $acceptedFileTypes,
        int $maxSize,
    ): FileUpload {
        $field = FileUpload::make($name)
            ->disk((string) config('filament-page-blocks.media.disk', 'public'))
            ->directory($directory ?? (string) config('filament-page-blocks.media.directory', 'page-blocks'))
            ->maxSize($maxSize);

        if ($acceptedFileTypes !== []) {
            $field->acceptedFileTypes($acceptedFileTypes);
        }

        return $this->configure($field, $default, $required, $label);
    }

    /** @param class-string<Model> $model */
    private function modelInstance(string $model): Model
    {
        if (! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException("Relationship model [{$model}] must extend Eloquent Model.");
        }

        return new $model;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $searchColumns
     * @return array<int|string, string>
     */
    private function relationOptions(
        string $model,
        string $titleAttribute,
        string $keyAttribute,
        ?Closure $modifyQueryUsing,
        array $searchColumns,
        ?string $search = null,
        int $limit = 50,
    ): array {
        $query = $this->relationQuery($model, $modifyQueryUsing);
        if (filled($search)) {
            $query->where(static function (Builder $query) use ($searchColumns, $search): void {
                foreach ($searchColumns as $index => $column) {
                    $query->{$index === 0 ? 'where' : 'orWhere'}($column, 'like', '%'.$search.'%');
                }
            });
        }

        return $query->limit($limit)->get()
            ->mapWithKeys(fn (Model $record): array => [
                $record->getAttribute($keyAttribute) => (string) data_get($record, str_replace('->', '.', $titleAttribute)),
            ])->all();
    }

    /** @param class-string<Model> $model */
    private function relationLabel(string $model, string $titleAttribute, string $keyAttribute, mixed $value, ?Closure $modifyQueryUsing): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $record = $this->relationQuery($model, $modifyQueryUsing)->where($keyAttribute, $value)->first();

        return $record === null ? null : (string) data_get($record, str_replace('->', '.', $titleAttribute));
    }

    /** @param class-string<Model> $model @param array<int|string, mixed> $values @return array<int|string, string> */
    private function relationLabels(string $model, string $titleAttribute, string $keyAttribute, array $values, ?Closure $modifyQueryUsing): array
    {
        if ($values === []) {
            return [];
        }

        return $this->relationQuery($model, $modifyQueryUsing)->whereIn($keyAttribute, $values)->get()
            ->mapWithKeys(fn (Model $record): array => [
                $record->getAttribute($keyAttribute) => (string) data_get($record, str_replace('->', '.', $titleAttribute)),
            ])->all();
    }

    /** @param class-string<Model> $model */
    private function relationQuery(string $model, ?Closure $modifyQueryUsing): Builder
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

    /**
     * @param  array<class-string<Model>, string>  $types
     * @return array<int|string, string>
     */
    private function morphOptions(array $types, mixed $selectedType, ?Closure $modifyQueryUsing, ?string $search = null, int $limit = 50): array
    {
        if (! is_string($selectedType) || ! isset($types[$selectedType])) {
            return [];
        }

        $model = $this->modelInstance($selectedType);

        return $this->relationOptions(
            model: $selectedType,
            titleAttribute: $types[$selectedType],
            keyAttribute: $model->getKeyName(),
            modifyQueryUsing: $modifyQueryUsing,
            searchColumns: [$types[$selectedType]],
            search: $search,
            limit: $limit,
        );
    }

    /** @param array<class-string<Model>, string> $types */
    private function morphLabel(array $types, mixed $selectedType, mixed $value, ?Closure $modifyQueryUsing): ?string
    {
        if (! is_string($selectedType) || ! isset($types[$selectedType])) {
            return null;
        }

        $model = $this->modelInstance($selectedType);

        return $this->relationLabel($selectedType, $types[$selectedType], $model->getKeyName(), $value, $modifyQueryUsing);
    }

    /**
     * @template T of Field
     *
     * @param  T  $field
     * @param  array<int, mixed>|string  $rules
     * @return T
     */
    private function configure(Field $field, mixed $default, bool $required = false, ?string $label = null, array|string $rules = []): Field
    {
        $field->default($default)->required($required);
        if ($label !== null) {
            $field->label($label);
        }
        if ($rules !== []) {
            $field->rules($rules);
        }

        return $field;
    }
}
