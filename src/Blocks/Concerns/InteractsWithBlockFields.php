<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Blocks\Concerns;

use Closure;
use DannAPI\FilamentPageBlocks\Support\BlockFields;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
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
use Illuminate\Database\Eloquent\Model;

trait InteractsWithBlockFields
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
    final protected static function integer(string $name, ?int $default = null, bool $required = false, ?int $minValue = null, ?int $maxValue = null, ?string $label = null, array|string $rules = []): TextInput
    {
        return static::fields()->integer($name, $default, $required, $minValue, $maxValue, $label, $rules);
    }

    /** @param array<int, mixed>|string $rules */
    final protected static function number(string $name, int|float|null $default = null, bool $required = false, int|float|null $minValue = null, int|float|null $maxValue = null, int|float|null $step = null, ?string $label = null, array|string $rules = []): TextInput
    {
        return static::fields()->number($name, $default, $required, $minValue, $maxValue, $step, $label, $rules);
    }

    /** @param array<int, mixed>|string $rules */
    final protected static function decimal(string $name, int|float|null $default = null, bool $required = false, int $decimalPlaces = 2, int|float|null $minValue = null, int|float|null $maxValue = null, ?string $label = null, array|string $rules = []): TextInput
    {
        return static::fields()->decimal($name, $default, $required, $decimalPlaces, $minValue, $maxValue, $label, $rules);
    }

    /** @param array<int, mixed>|string $rules */
    final protected static function money(string $name, int|float|null $default = null, bool $required = false, string $currency = 'USD', int $decimalPlaces = 2, int|float|null $minValue = 0, int|float|null $maxValue = null, ?string $label = null, array|string $rules = []): TextInput
    {
        return static::fields()->money($name, $default, $required, $currency, $decimalPlaces, $minValue, $maxValue, $label, $rules);
    }

    /** @param array<int, mixed>|string $rules */
    final protected static function textarea(string $name, mixed $default = '', bool $required = false, ?int $maxLength = null, ?int $rows = null, ?string $label = null, array|string $rules = []): Textarea
    {
        return static::fields()->textarea($name, $default, $required, $maxLength, $rows, $label, $rules);
    }

    /** @param array<int|string, string>|Closure $options */
    final protected static function select(string $name, array|Closure $options, mixed $default = null, bool $required = false, bool $multiple = false, ?string $label = null): Select
    {
        return static::fields()->select($name, $options, $default, $required, $multiple, $label);
    }

    /** @param array<int|string, string>|Closure $options */
    final protected static function radio(string $name, array|Closure $options, mixed $default = null, bool $required = false, ?string $label = null): Radio
    {
        return static::fields()->radio($name, $options, $default, $required, $label);
    }

    /** @param array<int|string, string>|Closure $options */
    final protected static function toggleButtons(string $name, array|Closure $options, mixed $default = null, bool $required = false, ?string $label = null): ToggleButtons
    {
        return static::fields()->toggleButtons($name, $options, $default, $required, $label);
    }

    /** @param array<int|string, string>|Closure $options */
    final protected static function checkboxList(string $name, array|Closure $options, array $default = [], bool $required = false, ?string $label = null): CheckboxList
    {
        return static::fields()->checkboxList($name, $options, $default, $required, $label);
    }

    final protected static function checkbox(string $name, bool $default = false, bool $required = false, ?string $label = null): Checkbox
    {
        return static::fields()->checkbox($name, $default, $required, $label);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $searchColumns
     */
    final protected static function relationship(
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
        ?Closure $configureUsing = null,
    ): Select {
        return static::fields()->relationship(
            $name,
            $model,
            $titleAttribute,
            $multiple,
            $default,
            $required,
            $label,
            $keyAttribute,
            $modifyQueryUsing,
            $searchColumns,
            $preload,
            $optionsLimit,
            $configureUsing,
        );
    }

    /** @param class-string<Model> $model */
    final protected static function belongsTo(string $name, string $model, string $titleAttribute = 'name', mixed $default = null, bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::relationship($name, $model, $titleAttribute, default: $default, required: $required, label: $label, modifyQueryUsing: $modifyQueryUsing, preload: $preload);
    }

    /** @param class-string<Model> $model */
    final protected static function hasOne(string $name, string $model, string $titleAttribute = 'name', mixed $default = null, bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsTo($name, $model, $titleAttribute, $default, $required, $label, $modifyQueryUsing, $preload);
    }

    /** @param class-string<Model> $model */
    final protected static function hasOneThrough(string $name, string $model, string $titleAttribute = 'name', mixed $default = null, bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsTo($name, $model, $titleAttribute, $default, $required, $label, $modifyQueryUsing, $preload);
    }

    /** @param class-string<Model> $model */
    final protected static function morphOne(string $name, string $model, string $titleAttribute = 'name', mixed $default = null, bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsTo($name, $model, $titleAttribute, $default, $required, $label, $modifyQueryUsing, $preload);
    }

    /** @param class-string<Model> $model */
    final protected static function belongsToMany(string $name, string $model, string $titleAttribute = 'name', array $default = [], bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::relationship($name, $model, $titleAttribute, multiple: true, default: $default, required: $required, label: $label, modifyQueryUsing: $modifyQueryUsing, preload: $preload);
    }

    /** @param class-string<Model> $model */
    final protected static function hasMany(string $name, string $model, string $titleAttribute = 'name', array $default = [], bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsToMany($name, $model, $titleAttribute, $default, $required, $label, $modifyQueryUsing, $preload);
    }

    /** @param class-string<Model> $model */
    final protected static function hasManyThrough(string $name, string $model, string $titleAttribute = 'name', array $default = [], bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsToMany($name, $model, $titleAttribute, $default, $required, $label, $modifyQueryUsing, $preload);
    }

    /** @param class-string<Model> $model */
    final protected static function morphMany(string $name, string $model, string $titleAttribute = 'name', array $default = [], bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsToMany($name, $model, $titleAttribute, $default, $required, $label, $modifyQueryUsing, $preload);
    }

    /** @param class-string<Model> $model */
    final protected static function morphToMany(string $name, string $model, string $titleAttribute = 'name', array $default = [], bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsToMany($name, $model, $titleAttribute, $default, $required, $label, $modifyQueryUsing, $preload);
    }

    /** @param class-string<Model> $model */
    final protected static function morphedByMany(string $name, string $model, string $titleAttribute = 'name', array $default = [], bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false): Select
    {
        return static::belongsToMany($name, $model, $titleAttribute, $default, $required, $label, $modifyQueryUsing, $preload);
    }

    /**
     * @param  array<class-string<Model>, string|array{title: string, label?: string}>  $types
     * @return array{Select, Select}
     */
    final protected static function morphTo(string $name, array $types, mixed $defaultType = null, mixed $defaultId = null, bool $required = false, ?string $label = null, ?Closure $modifyQueryUsing = null, bool $preload = false, int $optionsLimit = 50): array
    {
        return static::fields()->morphTo($name, $types, $defaultType, $defaultId, $required, $label, $modifyQueryUsing, $preload, $optionsLimit);
    }

    final protected static function toggle(string $name, bool $default = false, bool $required = false, ?string $label = null): Toggle
    {
        return static::fields()->toggle($name, $default, $required, $label);
    }

    final protected static function richText(string $name, mixed $default = '', bool $required = false, ?string $label = null): RichEditor
    {
        return static::fields()->richText($name, $default, $required, $label);
    }

    final protected static function markdown(string $name, mixed $default = '', bool $required = false, ?string $label = null): MarkdownEditor
    {
        return static::fields()->markdown($name, $default, $required, $label);
    }

    final protected static function code(string $name, mixed $default = '', bool $required = false, ?string $label = null): CodeEditor
    {
        return static::fields()->code($name, $default, $required, $label);
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

    final protected static function color(string $name, mixed $default = null, bool $required = false, ?string $label = null): ColorPicker
    {
        return static::fields()->color($name, $default, $required, $label);
    }

    final protected static function tags(string $name, array $default = [], bool $required = false, ?string $label = null): TagsInput
    {
        return static::fields()->tags($name, $default, $required, $label);
    }

    final protected static function keyValue(string $name, array $default = [], bool $required = false, ?string $label = null): KeyValue
    {
        return static::fields()->keyValue($name, $default, $required, $label);
    }

    final protected static function slider(string $name, int|float|null $default = null, bool $required = false, ?string $label = null): Slider
    {
        return static::fields()->slider($name, $default, $required, $label);
    }

    final protected static function hidden(string $name, mixed $default = null): Hidden
    {
        return static::fields()->hidden($name, $default);
    }

    /** @param array<Component>|Closure $schema */
    final protected static function repeater(string $name, array|Closure $schema, array $default = [], bool $required = false, ?string $label = null): Repeater
    {
        return static::fields()->repeater($name, $schema, $default, $required, $label);
    }

    final protected static function image(string $name, mixed $default = null, bool $required = false, ?string $label = null, ?string $directory = null): FileUpload
    {
        return static::fields()->image($name, $default, $required, $label, $directory);
    }

    final protected static function video(string $name, mixed $default = null, bool $required = false, ?string $label = null, ?string $directory = null): FileUpload
    {
        return static::fields()->video($name, $default, $required, $label, $directory);
    }

    final protected static function file(string $name, mixed $default = null, bool $required = false, bool $multiple = false, ?string $label = null, ?string $directory = null): FileUpload
    {
        return static::fields()->file($name, $default, $required, $multiple, $label, $directory);
    }

    /** @return array{FileUpload, TextInput} */
    final protected static function imageSource(string $upload = 'image', string $external = 'external_image', mixed $uploadDefault = null, mixed $externalDefault = null, bool $required = false, ?string $uploadLabel = 'Upload image', ?string $externalLabel = 'Stored path or HTTPS URL', ?string $directory = null): array
    {
        return static::fields()->imageSource($upload, $external, $uploadDefault, $externalDefault, $required, $uploadLabel, $externalLabel, $directory);
    }

    /** @return array{FileUpload, TextInput} */
    final protected static function videoSource(string $upload = 'video', string $external = 'external_video', mixed $uploadDefault = null, mixed $externalDefault = null, bool $required = false, ?string $uploadLabel = 'Upload video', ?string $externalLabel = 'Stored path or HTTPS URL', ?string $directory = null): array
    {
        return static::fields()->videoSource($upload, $external, $uploadDefault, $externalDefault, $required, $uploadLabel, $externalLabel, $directory);
    }

    /** @return array{FileUpload, TextInput} */
    final protected static function fileSource(string $upload = 'file', string $external = 'external_file', mixed $uploadDefault = null, mixed $externalDefault = null, bool $required = false, ?string $uploadLabel = 'Upload file', ?string $externalLabel = 'Stored path or HTTPS URL', ?string $directory = null): array
    {
        return static::fields()->fileSource($upload, $external, $uploadDefault, $externalDefault, $required, $uploadLabel, $externalLabel, $directory);
    }
}
