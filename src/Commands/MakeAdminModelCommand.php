<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;
use RuntimeException;
use Throwable;

final class MakeAdminModelCommand extends Command
{
    protected $signature = 'make:admin-model {name : Model name, for example Author or Catalog/Author}
        {--panel= : Filament panel ID}
        {--record-title-attribute= : Column used as the record title}
        {--view : Add a read-only view operation}
        {--soft-deletes : Generate soft-delete controls}
        {--no-policy : Do not create a policy or register role permissions}
        {--force : Overwrite generated Filament, policy, and permission files}';

    protected $description = 'Generate a compact Filament CRUD for an existing migrated application model';

    public function handle(Filesystem $files): int
    {
        $relativeName = $this->normalizeName((string) $this->argument('name'));
        if ($relativeName === null) {
            $this->components->error('Use a valid class name such as Author or Catalog/Author. Paths containing dots or traversal are not allowed.');

            return self::INVALID;
        }

        $modelNamespace = trim((string) config('filament-page-blocks.generator.admin_model.model_namespace', 'App\\Models'), '\\');
        $modelFqn = $modelNamespace.'\\'.$relativeName;

        if (! class_exists($modelFqn)) {
            $this->components->error("Model [{$modelFqn}] does not exist.");
            $this->line("Create it first with: php artisan make:model {$relativeName} -m");

            return self::FAILURE;
        }

        if (! is_subclass_of($modelFqn, Model::class)) {
            $this->components->error("Class [{$modelFqn}] must extend Eloquent Model.");

            return self::FAILURE;
        }

        /** @var Model $model */
        $model = new $modelFqn;
        $schema = $model->getConnection()->getSchemaBuilder();
        $table = $model->getTable();
        if (! $schema->hasTable($table)) {
            $this->components->error("Table [{$table}] does not exist. Fill the generated migration and run [php artisan migrate], then run this command again.");

            return self::FAILURE;
        }

        $columns = $schema->getColumnListing($table);
        $columnDefinitions = $schema->getColumns($table);
        try {
            $recordTitleAttribute = $this->recordTitleAttribute($columns);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
        $panel = (string) ($this->option('panel') ?: config('filament-page-blocks.generator.admin_model.panel', 'admin'));
        $resourceNamespace = trim((string) config('filament-page-blocks.generator.admin_model.resource_namespace', 'App\\Filament\\Resources'), '\\');

        $resourcePath = $this->resourcePath($relativeName);
        if ($files->isFile($resourcePath) && ! $this->option('force')) {
            $this->components->error("Resource already exists and was not changed: {$resourcePath}");
            $this->line('Run the command with --force only when you intentionally want to regenerate it.');

            return self::FAILURE;
        }

        $result = $this->call('make:filament-resource', array_filter([
            'model' => $relativeName,
            '--model-namespace' => $modelNamespace,
            '--resource-namespace' => $resourceNamespace,
            '--panel' => $panel,
            '--record-title-attribute' => $recordTitleAttribute,
            '--generate' => true,
            '--simple' => true,
            '--view' => (bool) $this->option('view'),
            '--soft-deletes' => (bool) $this->option('soft-deletes'),
            '--force' => (bool) $this->option('force'),
            '--no-interaction' => true,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        if ($result !== self::SUCCESS) {
            return $result;
        }

        try {
            $this->addAdminFieldHelpers($files, $relativeName, $columnDefinitions);
            $this->configureGeneratedModalWidth($files, $relativeName);
            $this->configureFillable($files, $model, $columns);
            if (! $this->option('no-policy')) {
                $this->createPolicyAndPermissions($files, $modelFqn, $relativeName);
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Compact Filament CRUD generated from the current database schema.');
        $this->line("The resource is discovered by panel [{$panel}] and will appear in its navigation.");
        $this->line('Edit form() and table() directly in the generated Resource; InteractsWithAdminFields is already enabled.');
        $this->callSilently('filament:clear-cached-components');

        return self::SUCCESS;
    }

    private function normalizeName(string $name): ?string
    {
        $name = trim(str_replace('\\', '/', $name), '/ ');
        if (preg_match('/^[A-Za-z][A-Za-z0-9]*(?:\/[A-Za-z][A-Za-z0-9]*)*$/', $name) !== 1) {
            return null;
        }

        return collect(explode('/', $name))->map(static fn (string $part): string => Str::studly($part))->implode('\\');
    }

    /** @param array<int, array<string, mixed>> $columns */
    private function addAdminFieldHelpers(Filesystem $files, string $relativeName, array $columns): void
    {
        $modelClass = class_basename($relativeName);
        $path = $this->resourcePath($relativeName);
        if (! $files->isFile($path)) {
            throw new RuntimeException("Unable to locate the generated Resource: {$path}");
        }

        $contents = $files->get($path);
        $traitUsage = '    use \\DannAPI\\FilamentPageBlocks\\Filament\\Concerns\\InteractsWithAdminFields;';
        if (! str_contains($contents, $traitUsage)) {
            $resourceClass = preg_quote($modelClass.'Resource', '/');
            $contents = preg_replace(
                '/(class\s+'.$resourceClass.'\s+extends\s+Resource\s*\{\R)/',
                '$1'.$traitUsage.PHP_EOL.PHP_EOL,
                $contents,
                1,
                $classCount,
            );
            if (! is_string($contents) || $classCount !== 1) {
                throw new RuntimeException('Unable to enable InteractsWithAdminFields in the generated Resource.');
            }
        }

        $contents = $this->useAdminFieldHelpers($contents, $columns);
        $contents = $this->wrapGeneratedForm($contents, $this->mediaColumnNames($columns));

        $files->put($path, $contents, true);
        $this->components->info("Enabled admin field helpers in: {$path}");
    }

    /** @param array<int, array<string, mixed>> $columns */
    private function useAdminFieldHelpers(string $contents, array $columns): string
    {
        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $type = $this->normalizedColumnType($column);
            $formHelper = $this->formHelperFor($name, $type);
            if ($formHelper !== null) {
                $quotedName = preg_quote(var_export($name, true), '/');
                $contents = preg_replace(
                    '/(?:TextInput|Textarea|FileUpload)::make\\('.$quotedName.'\\)/',
                    "self::{$formHelper}(".var_export($name, true).')',
                    $contents,
                    1,
                ) ?? $contents;
            }

            $tableHelper = $this->tableHelperFor($name, $type);
            if ($tableHelper !== null) {
                $quotedName = preg_quote(var_export($name, true), '/');
                $contents = preg_replace(
                    '/TextColumn::make\\('.$quotedName.'\\)/',
                    "self::{$tableHelper}(".var_export($name, true).')',
                    $contents,
                    1,
                ) ?? $contents;
            }
        }

        $components = [
            'TextInput' => 'text',
            'Textarea' => 'textarea',
            'Select' => 'select',
            'Toggle' => 'toggle',
            'DatePicker' => 'date',
            'DateTimePicker' => 'dateTime',
            'TimePicker' => 'time',
            'FileUpload' => 'image',
            'Checkbox' => 'checkbox',
            'CheckboxList' => 'checkboxList',
            'Radio' => 'radio',
            'ToggleButtons' => 'toggleButtons',
            'TagsInput' => 'tags',
            'KeyValue' => 'keyValue',
            'MarkdownEditor' => 'markdown',
            'CodeEditor' => 'code',
            'ColorPicker' => 'color',
            'Slider' => 'slider',
            'Hidden' => 'hidden',
            'TextColumn' => 'textColumn',
            'IconColumn' => 'booleanColumn',
            'ImageColumn' => 'imageColumn',
        ];

        foreach ($components as $component => $helper) {
            $contents = str_replace("{$component}::make(", "self::{$helper}(", $contents);
        }
        $contents = str_replace('static::formLayout(', 'self::formLayout(', $contents);

        $imports = [
            'Filament\\Forms\\Components\\TextInput',
            'Filament\\Forms\\Components\\Textarea',
            'Filament\\Forms\\Components\\Select',
            'Filament\\Forms\\Components\\Toggle',
            'Filament\\Forms\\Components\\DatePicker',
            'Filament\\Forms\\Components\\DateTimePicker',
            'Filament\\Forms\\Components\\TimePicker',
            'Filament\\Forms\\Components\\FileUpload',
            'Filament\\Forms\\Components\\Checkbox',
            'Filament\\Forms\\Components\\CheckboxList',
            'Filament\\Forms\\Components\\Radio',
            'Filament\\Forms\\Components\\ToggleButtons',
            'Filament\\Forms\\Components\\TagsInput',
            'Filament\\Forms\\Components\\KeyValue',
            'Filament\\Forms\\Components\\MarkdownEditor',
            'Filament\\Forms\\Components\\CodeEditor',
            'Filament\\Forms\\Components\\ColorPicker',
            'Filament\\Forms\\Components\\Slider',
            'Filament\\Forms\\Components\\Hidden',
            'Filament\\Tables\\Columns\\TextColumn',
            'Filament\\Tables\\Columns\\IconColumn',
            'Filament\\Tables\\Columns\\ImageColumn',
        ];

        foreach ($imports as $import) {
            $class = class_basename($import);
            if (! str_contains($contents, "{$class}::")) {
                $contents = preg_replace('/^use '.preg_quote($import, '/').';\R/m', '', $contents) ?? $contents;
            }
        }

        return $contents;
    }

    /** @param array<int, string> $mediaColumns */
    private function wrapGeneratedForm(string $contents, array $mediaColumns): string
    {
        if (str_contains($contents, 'static::formLayout(') || str_contains($contents, 'self::formLayout(')) {
            return $contents;
        }

        $pattern = '/(?<prefix>public static function form\(Schema \$schema\): Schema\s*\{\s*return \$schema\s*->components\(\[)\s*(?<fields>.*?)(?<suffix>\s*\]\);\s*\})/s';
        $updated = preg_replace_callback(
            $pattern,
            static function (array $matches) use ($mediaColumns): string {
                $components = preg_split('/(?=^\\s{16}(?:self::[A-Za-z][A-Za-z0-9_]*|[A-Za-z][A-Za-z0-9_]*::make)\\()/m', trim((string) $matches['fields'])) ?: [];
                $main = [];
                $sidebar = [];

                foreach ($components as $component) {
                    $component = trim($component);
                    if ($component === '') {
                        continue;
                    }

                    preg_match('/(?:self::[A-Za-z][A-Za-z0-9_]*|[A-Za-z][A-Za-z0-9_]*::make)\\(\'([^\']+)\'\\)/', $component, $fieldMatch);
                    $fieldName = (string) ($fieldMatch[1] ?? '');
                    if ($fieldName !== '' && in_array($fieldName, $mediaColumns, true)) {
                        $sidebar[] = $component;
                    } else {
                        $main[] = $component;
                    }
                }

                $format = static function (array $fields): string {
                    return implode(PHP_EOL, array_map(
                        static function (string $field): string {
                            $field = preg_replace('/^ {16}/m', '', $field) ?? $field;

                            return preg_replace('/^/m', '                        ', $field) ?? $field;
                        },
                        $fields,
                    ));
                };

                $mainFields = $format($main);
                $sidebarOutput = $sidebar === []
                    ? ''
                    : '                    sidebar: ['.PHP_EOL.$format($sidebar).PHP_EOL.'                    ],'.PHP_EOL;

                return $matches['prefix'].PHP_EOL
                    .'                self::formLayout('.PHP_EOL
                    .'                    main: ['.PHP_EOL
                    .$mainFields.PHP_EOL
                    .'                    ],'.PHP_EOL
                    .$sidebarOutput
                    .'                ),'.PHP_EOL
                    .'            ]);'.PHP_EOL
                    .'    }';
            },
            $contents,
            1,
            $count,
        );

        if (! is_string($updated) || $count !== 1) {
            throw new RuntimeException('Unable to wrap generated form fields in formLayout().');
        }

        return $updated;
    }

    private function resourcePath(string $relativeName): string
    {
        $resourceRoot = rtrim((string) config('filament-page-blocks.generator.admin_model.resource_path', app_path('Filament/Resources')), '/');
        $directory = str_replace('\\', '/', Str::pluralStudly($relativeName));
        $modelClass = class_basename($relativeName);

        return "{$resourceRoot}/{$directory}/{$modelClass}Resource.php";
    }

    /** @param array<int, array<string, mixed>> $columns @return array<int, string> */
    private function mediaColumnNames(array $columns): array
    {
        return array_values(array_filter(array_map(
            fn (array $column): ?string => $this->isMediaColumn((string) ($column['name'] ?? '')) ? (string) $column['name'] : null,
            $columns,
        )));
    }

    /** @param array<string, mixed> $column */
    private function normalizedColumnType(array $column): string
    {
        $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? 'string'));

        return match ($type) {
            'bool', 'boolean', 'bit', 'tinyint(1)' => 'boolean',
            'int', 'int2', 'int4', 'int8', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint' => 'integer',
            'decimal', 'numeric' => 'decimal',
            'float', 'double', 'real', 'float4', 'float8' => 'float',
            'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset', 'timestamp', 'timestamptz' => 'datetime',
            'json', 'jsonb' => 'json',
            default => $type,
        };
    }

    private function formHelperFor(string $name, string $type): ?string
    {
        if ($this->isImageColumn($name)) {
            return 'image';
        }
        if ($this->isVideoColumn($name)) {
            return 'video';
        }
        if ($this->isFileColumn($name)) {
            return 'file';
        }
        if ($type === 'json') {
            return str_contains(strtolower($name), 'tag') ? 'tags' : 'keyValue';
        }
        if (in_array($type, ['text', 'longtext', 'mediumtext'], true) && preg_match('/(?:^|_)(body|content|description|details|overview)(?:$|_)/i', $name) === 1) {
            return 'richText';
        }
        if ($type === 'integer') {
            return 'integer';
        }
        if (in_array($type, ['decimal', 'float', 'money'], true)) {
            return $this->isMoneyColumn($name, $type) ? 'money' : 'decimal';
        }

        return null;
    }

    private function tableHelperFor(string $name, string $type): ?string
    {
        if ($type === 'date') {
            return 'dateColumn';
        }
        if ($type === 'datetime') {
            return 'dateTimeColumn';
        }
        if ($type === 'enum') {
            return 'badgeColumn';
        }
        if (in_array($type, ['integer', 'decimal', 'float', 'money'], true)) {
            return $this->isMoneyColumn($name, $type) ? 'moneyColumn' : 'numericColumn';
        }

        return null;
    }

    private function isMediaColumn(string $name): bool
    {
        return $this->isImageColumn($name) || $this->isVideoColumn($name) || $this->isFileColumn($name);
    }

    private function isImageColumn(string $name): bool
    {
        return $this->matchesMediaName($name, ['image', 'photo', 'picture', 'avatar', 'poster', 'thumbnail', 'logo', 'cover']);
    }

    private function isVideoColumn(string $name): bool
    {
        return $this->matchesMediaName($name, ['video', 'movie', 'clip']);
    }

    private function isFileColumn(string $name): bool
    {
        return $this->matchesMediaName($name, ['file', 'document', 'attachment', 'download']);
    }

    /** @param array<int, string> $needles */
    private function matchesMediaName(string $name, array $needles): bool
    {
        $lower = strtolower($name);
        if (str_ends_with($lower, '_url') || str_starts_with($lower, 'external_') || str_ends_with($lower, '_source')) {
            return false;
        }

        foreach ($needles as $needle) {
            if (preg_match('/(?:^|_)'.preg_quote($needle, '/').'(?:$|_)/', $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isMoneyColumn(string $name, string $type): bool
    {
        return $type === 'money' || preg_match('/(?:^|_)(price|cost|amount|total)(?:$|_)/i', $name) === 1;
    }

    private function configureGeneratedModalWidth(Filesystem $files, string $relativeName): void
    {
        $resourceRoot = rtrim((string) config('filament-page-blocks.generator.admin_model.resource_path', app_path('Filament/Resources')), '/');
        $directory = str_replace('\\', '/', Str::pluralStudly($relativeName));
        $modelClass = class_basename($relativeName);
        $width = (string) config('filament-page-blocks.generator.admin_model.modal_width', '5xl');
        $targets = [
            "{$resourceRoot}/{$directory}/{$modelClass}Resource.php" => 'EditAction::make()',
            "{$resourceRoot}/{$directory}/Pages/Manage".Str::pluralStudly($modelClass).'.php' => 'CreateAction::make()',
        ];

        foreach ($targets as $path => $action) {
            if (! $files->isFile($path)) {
                continue;
            }

            $contents = $files->get($path);
            $replacement = $action.'->modalWidth('.var_export($width, true).')';
            if (! str_contains($contents, $replacement)) {
                $contents = preg_replace('/'.preg_quote($action, '/').'(?!->modalWidth)/', $replacement, $contents, 1) ?? $contents;
                $files->put($path, $contents, true);
            }
        }
    }

    /** @param array<int, string> $columns */
    private function recordTitleAttribute(array $columns): string
    {
        $requested = $this->option('record-title-attribute');
        if (is_string($requested) && $requested !== '') {
            if (! in_array($requested, $columns, true)) {
                throw new RuntimeException("Record title column [{$requested}] does not exist in the migrated table.");
            }

            return $requested;
        }

        foreach (['name', 'title', 'label', 'email', 'slug', 'id'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return $columns[0] ?? 'id';
    }

    /** @param array<int, string> $columns */
    private function configureFillable(Filesystem $files, Model $model, array $columns): void
    {
        if ($model->getFillable() !== [] || $model->getGuarded() !== ['*']) {
            $this->components->info('Model mass-assignment configuration already exists; it was left unchanged.');

            return;
        }

        $excluded = array_filter([
            $model->getKeyName(),
            $model->getCreatedAtColumn(),
            $model->getUpdatedAtColumn(),
            'deleted_at',
            'remember_token',
        ]);
        $fillable = array_values(array_diff($columns, $excluded));
        $reflection = new ReflectionClass($model);
        $path = $reflection->getFileName();
        if (! is_string($path) || ! $files->isFile($path)) {
            throw new RuntimeException('Unable to locate the generated model file for mass-assignment configuration.');
        }

        $contents = $files->get($path);
        $class = preg_quote($reflection->getShortName(), '/');
        $property = '    protected $fillable = ['.PHP_EOL
            .implode('', array_map(static fn (string $column): string => "        '{$column}',".PHP_EOL, $fillable))
            .'    ];'.PHP_EOL.PHP_EOL;
        $updated = preg_replace('/(class\s+'.$class.'\s+extends\s+[^\{]+\{\R)/', '$1'.$property, $contents, 1, $count);
        if (! is_string($updated) || $count !== 1) {
            throw new RuntimeException('Unable to add $fillable to the model safely. Configure mass assignment manually and rerun with --force.');
        }

        $files->put($path, $updated, true);
        $this->components->info("Configured fillable columns in: {$path}");
    }

    private function createPolicyAndPermissions(Filesystem $files, string $modelFqn, string $relativeName): void
    {
        $modelClass = class_basename($modelFqn);
        $relativeNamespace = str_contains($relativeName, '\\') ? Str::beforeLast($relativeName, '\\') : '';
        $policyRootNamespace = trim((string) config('filament-page-blocks.generator.admin_model.policy_namespace', 'App\\Policies'), '\\');
        $policyNamespace = $policyRootNamespace.($relativeNamespace === '' ? '' : '\\'.$relativeNamespace);
        $policyRootPath = rtrim((string) config('filament-page-blocks.generator.admin_model.policy_path', app_path('Policies')), '/');
        $policyPath = $policyRootPath.($relativeNamespace === '' ? '' : '/'.str_replace('\\', '/', $relativeNamespace))."/{$modelClass}Policy.php";
        $permissionPrefix = Str::plural(Str::snake($modelClass));
        $group = Str::headline(Str::pluralStudly($modelClass));

        if (! $files->exists($policyPath) || $this->option('force')) {
            $stub = $files->get(dirname(__DIR__, 2).'/stubs/admin-resource-policy.stub');
            $contents = strtr($stub, [
                '{{ policyNamespace }}' => $policyNamespace,
                '{{ modelFqn }}' => $modelFqn,
                '{{ modelClass }}' => $modelClass,
                '{{ permissionPrefix }}' => $permissionPrefix,
            ]);
            $files->ensureDirectoryExists(dirname($policyPath));
            $files->put($policyPath, $contents, true);
            $this->components->info("Created policy: {$policyPath}");
        } else {
            $this->components->warn("Policy already exists and was not overwritten: {$policyPath}");
        }

        $permissions = [
            'viewAny' => "View {$group} list",
            'view' => "View {$group}",
            'create' => "Create {$group}",
            'update' => "Update {$group}",
            'delete' => "Delete {$group}",
            'restore' => "Restore {$group}",
            'forceDelete' => "Force-delete {$group}",
        ];
        $permissionsRoot = rtrim(
            (string) config('filament-page-blocks.generator.admin_model.permissions_path', app_path('Filament/Permissions')),
            '/',
        );
        $permissionsDirectory = $permissionsRoot.($relativeNamespace === '' ? '' : '/'.str_replace('\\', '/', $relativeNamespace));
        $permissionsPath = $permissionsDirectory.'/'.Str::snake(Str::pluralStudly($modelClass)).'.php';

        if ($files->exists($permissionsPath) && ! $this->option('force')) {
            $this->components->warn("Permission definition already exists and was not overwritten: {$permissionsPath}");

            return;
        }

        $lines = implode('', array_map(
            static fn (string $action, string $label): string => "        '{$permissionPrefix}.{$action}' => '{$label}',".PHP_EOL,
            array_keys($permissions),
            $permissions,
        ));
        $stub = $files->get(dirname(__DIR__, 2).'/stubs/admin-resource-permissions.stub');
        $contents = strtr($stub, [
            '{{ group }}' => $group,
            '{{ permissions }}' => $lines,
        ]);
        $files->ensureDirectoryExists($permissionsDirectory);
        $files->put($permissionsPath, $contents, true);
        $this->components->info("Created permission definition: {$permissionsPath}");
    }
}
