<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Blocks\Concerns;

use Closure;
use DannAPI\FilamentPageBlocks\Support\BlockFields;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
    final protected static function textarea(string $name, mixed $default = '', bool $required = false, ?int $maxLength = null, ?int $rows = null, ?string $label = null, array|string $rules = []): Textarea
    {
        return static::fields()->textarea($name, $default, $required, $maxLength, $rows, $label, $rules);
    }

    /** @param array<int|string, string>|Closure $options */
    final protected static function select(string $name, array|Closure $options, mixed $default = null, bool $required = false, bool $multiple = false, ?string $label = null): Select
    {
        return static::fields()->select($name, $options, $default, $required, $multiple, $label);
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
}
