<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Concerns;

use Closure;
use DannAPI\FilamentPageBlocks\Support\BlockFields;
use DannAPI\FilamentPageBlocks\Support\RichTextExcerpt;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait InteractsWithAdminFields
{
    final protected static function fields(): BlockFields
    {
        return app(BlockFields::class);
    }

    /** @param array<int, mixed>|string $rules */
    final protected static function text(string $name, mixed $default = '', bool $required = false, ?int $maxLength = null, ?string $label = null, array|string $rules = []): TextInput
    {
        return static::fields()->text($name, $default, $required, $maxLength, $label, $rules);
    }

    /** @param array<int, mixed>|string $rules */
    final protected static function textarea(string $name, mixed $default = '', bool $required = false, ?int $maxLength = null, ?int $rows = null, ?string $label = null, array|string $rules = []): Textarea
    {
        return static::fields()->textarea($name, $default, $required, $maxLength, $rows, $label, $rules);
    }

    /** @param array<int|string, string>|Closure $options */
    final protected static function select(string $name, array|Closure $options = [], mixed $default = null, bool $required = false, bool $multiple = false, ?string $label = null): Select
    {
        return static::fields()->select($name, $options, $default, $required, $multiple, $label);
    }

    final protected static function toggle(string $name, bool $default = false, bool $required = false, ?string $label = null): Toggle
    {
        return static::fields()->toggle($name, $default, $required, $label);
    }

    final protected static function richText(string $name, mixed $default = '', bool $required = false, ?string $label = null): RichEditor
    {
        $field = RichEditor::make($name)
            ->default($default)
            ->required($required);

        if ($label !== null) {
            $field->label($label);
        }

        if (! config('filament-page-blocks.fields.rich_text.resizable', true)) {
            return $field;
        }

        $minHeight = (string) config('filament-page-blocks.fields.rich_text.min_height', '12rem');
        if (preg_match('/^\d+(?:\.\d+)?(?:px|rem|em|vh)$/', $minHeight) !== 1) {
            $minHeight = '12rem';
        }

        $direction = (string) config('filament-page-blocks.fields.rich_text.resize_direction', 'vertical');
        if (! in_array($direction, ['vertical', 'both'], true)) {
            $direction = 'vertical';
        }

        return $field->extraInputAttributes([
            'class' => 'fpb-admin-rich-editor-resizable',
            'style' => "--fpb-rich-editor-min-height: {$minHeight}; resize: {$direction}; overflow: auto; min-height: {$minHeight};",
        ], merge: true);
    }

    final protected static function date(string $name, mixed $default = null, bool $required = false, ?string $label = null): DatePicker
    {
        return static::fields()->date($name, $default, $required, $label);
    }

    final protected static function dateTime(string $name, mixed $default = null, bool $required = false, ?string $label = null): DateTimePicker
    {
        return static::fields()->dateTime($name, $default, $required, $label);
    }

    final protected static function time(string $name, mixed $default = null, bool $required = false, ?string $label = null): TimePicker
    {
        return static::fields()->time($name, $default, $required, $label);
    }

    /** @param array<Component>|Closure $schema */
    final protected static function repeater(string $name, array|Closure $schema, array $default = [], bool $required = false, ?string $label = null): Repeater
    {
        return static::fields()->repeater($name, $schema, $default, $required, $label);
    }

    final protected static function image(string $name, mixed $default = null, bool $required = false, ?string $label = null, ?string $directory = null): FileUpload
    {
        return static::fields()->image($name, $default, $required, $label, $directory)
            ->imagePreviewHeight((string) config('filament-page-blocks.media.image_preview_height', '220px'));
    }

    final protected static function video(string $name, mixed $default = null, bool $required = false, ?string $label = null, ?string $directory = null): FileUpload
    {
        return static::fields()->video($name, $default, $required, $label, $directory);
    }

    final protected static function file(string $name, mixed $default = null, bool $required = false, bool $multiple = false, ?string $label = null, ?string $directory = null): FileUpload
    {
        return static::fields()->file($name, $default, $required, $multiple, $label, $directory);
    }

    /**
     * @param  array<Component>  $main
     * @param  array<Component>  $sidebar
     */
    final protected static function formLayout(
        array $main,
        array $sidebar = [],
        string $mainHeading = 'Content',
        string $sidebarHeading = 'Media',
    ): Grid {
        $hasSidebar = $sidebar !== [];
        $sections = [
            Section::make($mainHeading)
                ->schema($main)
                ->columns(1)
                ->columnSpan(['default' => 1, 'lg' => $hasSidebar ? 2 : 3]),
        ];

        if ($hasSidebar) {
            $sections[] = Section::make($sidebarHeading)
                ->schema($sidebar)
                ->columns(1)
                ->columnSpan(['default' => 1, 'lg' => 1]);
        }

        return Grid::make(['default' => 1, 'lg' => 3])
            ->schema($sections)
            ->columnSpanFull();
    }

    final protected static function relationship(
        string $name,
        string $relationship,
        string $titleAttribute = 'name',
        bool $multiple = false,
        bool $required = false,
        ?string $label = null,
        ?Closure $modifyQueryUsing = null,
        bool $preload = false,
    ): Select {
        $field = Select::make($name)
            ->relationship($relationship, $titleAttribute, $modifyQueryUsing)
            ->multiple($multiple)
            ->required($required)
            ->selectablePlaceholder(! $required)
            ->searchable()
            ->preload($preload);

        return $label === null ? $field : $field->label($label);
    }

    final protected static function belongsTo(string $name, string $relationship, string $titleAttribute = 'name', bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::relationship($name, $relationship, $titleAttribute, required: $required, label: $label, modifyQueryUsing: $modifyQueryUsing, preload: $preload);
    }

    final protected static function belongsToMany(string $name, string $relationship, string $titleAttribute = 'name', bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::relationship($name, $relationship, $titleAttribute, multiple: true, required: $required, label: $label, modifyQueryUsing: $modifyQueryUsing, preload: $preload);
    }

    final protected static function hasOne(string $name, string $relationship, string $titleAttribute = 'name', bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsTo($name, $relationship, $titleAttribute, $required, $label, $modifyQueryUsing, $preload);
    }

    final protected static function hasMany(string $name, string $relationship, string $titleAttribute = 'name', bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsToMany($name, $relationship, $titleAttribute, $required, $label, $modifyQueryUsing, $preload);
    }

    final protected static function morphOne(string $name, string $relationship, string $titleAttribute = 'name', bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsTo($name, $relationship, $titleAttribute, $required, $label, $modifyQueryUsing, $preload);
    }

    final protected static function morphMany(string $name, string $relationship, string $titleAttribute = 'name', bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsToMany($name, $relationship, $titleAttribute, $required, $label, $modifyQueryUsing, $preload);
    }

    final protected static function morphToMany(string $name, string $relationship, string $titleAttribute = 'name', bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsToMany($name, $relationship, $titleAttribute, $required, $label, $modifyQueryUsing, $preload);
    }

    final protected static function morphedByMany(string $name, string $relationship, string $titleAttribute = 'name', bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsToMany($name, $relationship, $titleAttribute, $required, $label, $modifyQueryUsing, $preload);
    }

    /**
     * @param  array<class-string<Model>, string|array{title: string, label?: string}>  $types
     */
    final protected static function morphTo(string $relationship, array $types, bool $required = false, ?string $label = null, bool $preload = false): MorphToSelect
    {
        $fieldTypes = [];
        foreach ($types as $model => $definition) {
            $title = is_array($definition) ? $definition['title'] : $definition;
            $type = Type::make($model)->titleAttribute($title);
            $type->label(is_array($definition) && isset($definition['label']) ? $definition['label'] : Str::headline(class_basename($model)));
            $fieldTypes[] = $type;
        }

        $field = MorphToSelect::make($relationship)
            ->types($fieldTypes)
            ->required($required)
            ->modifyTypeSelectUsing(static fn (Component $component): Component => $component instanceof Select
                ? $component->selectablePlaceholder(! $required)
                : $component)
            ->modifyKeySelectUsing(static fn (Select $component): Select => $component->selectablePlaceholder(! $required))
            ->searchable()
            ->preload($preload);

        return $label === null ? $field : $field->label($label);
    }

    final protected static function textColumn(string $name, bool $searchable = false, bool $sortable = false, ?string $label = null): TextColumn
    {
        $column = TextColumn::make($name)->searchable($searchable)->sortable($sortable);

        return $label === null ? $column : $column->label($label);
    }

    final protected static function richTextColumn(
        string $name,
        bool $searchable = false,
        bool $sortable = false,
        ?string $label = null,
        ?int $limit = null,
    ): TextColumn {
        $limit ??= max(1, (int) config('filament-page-blocks.fields.rich_text.table_limit', 120));
        $lineClamp = max(1, (int) config('filament-page-blocks.fields.rich_text.table_line_clamp', 2));
        $placeholder = (string) config('filament-page-blocks.fields.rich_text.table_placeholder', '—');

        return static::textColumn($name, $searchable, $sortable, $label)
            ->formatStateUsing(static fn (mixed $state): string => app(RichTextExcerpt::class)->plain($state))
            ->limit($limit)
            ->lineClamp($lineClamp)
            ->placeholder($placeholder);
    }

    final protected static function booleanColumn(string $name, ?string $label = null): IconColumn
    {
        $column = IconColumn::make($name)->boolean();

        return $label === null ? $column : $column->label($label);
    }

    final protected static function imageColumn(string $name, ?string $label = null, ?string $disk = null, ?string $visibility = null): ImageColumn
    {
        $disk ??= (string) config('filament-page-blocks.media.disk', 'public');
        $column = ImageColumn::make($name)->disk($disk);
        if ($visibility !== null) {
            $column->visibility($visibility);
        }

        return $label === null ? $column : $column->label($label);
    }
}
