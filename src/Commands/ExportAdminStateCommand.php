<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Commands;

use DannAPI\FilamentPageBlocks\Support\AdminStateExporter;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class ExportAdminStateCommand extends Command
{
    protected $signature = 'page-blocks:export-admin-state
        {--with-custom-pages : Include Pages created manually in Filament}
        {--class=AdminStateSeeder : Generated application seeder class name}
        {--force : Overwrite an existing generated seeder}';

    protected $description = 'Export non-secret Filament Page Blocks administration state to an application seeder';

    public function handle(AdminStateExporter $exporter, Filesystem $files): int
    {
        $class = $this->option('class');
        if (! is_string($class) || preg_match('/^[A-Z][A-Za-z0-9_]*$/', $class) !== 1) {
            $this->components->error('The seeder class must be a valid unqualified StudlyCase PHP class name.');

            return self::INVALID;
        }

        $path = database_path("seeders/{$class}.php");
        if ($files->exists($path) && ! $this->option('force')) {
            $this->components->error("Seeder already exists: {$path}. Use --force to overwrite it.");

            return self::FAILURE;
        }

        $state = $exporter->export((bool) $this->option('with-custom-pages'));
        $stub = $files->get(dirname(__DIR__, 2).'/stubs/admin-state-seeder.stub');
        $contents = str_replace(
            ['{{ class }}', '{{ state }}'],
            [$class, $this->exportArray($state)],
            $stub,
        );
        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents, true);

        $this->components->info("Admin state seeder created: {$path}");
        $this->table(['Dataset', 'Records'], [
            ['Roles', count((array) $state['roles'])],
            ['Users (no passwords)', count((array) $state['users'])],
            ['Menus', count((array) $state['menus'])],
            ['GeneralInfo', $state['general_info'] === null ? 0 : 1],
            ['Pages', count((array) $state['pages'])],
        ]);
        $this->components->warn('Passwords, remember tokens, sessions, numeric IDs, timestamps, and media file contents were not exported.');
        $this->line("Restore with: php artisan db:seed --class=Database\\Seeders\\{$class}");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $state */
    private function exportArray(array $state): string
    {
        return $this->exportValue($state, 2);
    }

    private function exportValue(mixed $value, int $depth = 0): string
    {
        if ($value === null) {
            return 'null';
        }
        if (! is_array($value)) {
            return var_export($value, true);
        }
        if ($value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth);
        $childIndent = str_repeat('    ', $depth + 1);
        $isList = array_is_list($value);
        $lines = [];
        foreach ($value as $key => $item) {
            $prefix = $isList ? '' : var_export($key, true).' => ';
            $lines[] = $childIndent.$prefix.$this->exportValue($item, $depth + 1).',';
        }

        return "[\n".implode("\n", $lines)."\n{$indent}]";
    }
}
