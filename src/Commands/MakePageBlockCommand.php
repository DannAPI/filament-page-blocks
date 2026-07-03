<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

final class MakePageBlockCommand extends Command
{
    protected $signature = 'make:page-block {name : Block name without the Block suffix}
        {--view : Also create a Blade view}
        {--all : Create the class and Blade view}
        {--package : Package-maintainer mode: create inside the package source instead of the host application}
        {--no-register : Do not update AppServiceProvider automatically}
        {--force : Overwrite existing files}';

    protected $description = 'Create and register a typed Filament page block';

    public function handle(Filesystem $files): int
    {
        $rawName = (string) $this->argument('name');
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $rawName) !== 1) {
            $this->components->error('The name must contain only letters, numbers, underscores, or hyphens and must not contain a path.');

            return self::INVALID;
        }

        $base = Str::studly(Str::beforeLast($rawName, 'Block'));
        if ($base === '') {
            $this->components->error('The block name is invalid.');

            return self::INVALID;
        }

        $class = $base.'Block';
        $identifier = Str::snake($base);
        $package = (bool) $this->option('package');

        if ($package && ! $this->isPackageDevelopmentMode()) {
            $this->components->error(
                'The --package option is available only in package development mode.'
            );

            return self::INVALID;
        }
        $withView = (bool) $this->option('view') || (bool) $this->option('all');
        $root = dirname(__DIR__, 2);

        $namespace = $package ? 'DannAPI\\FilamentPageBlocks\\Blocks' : trim((string) config('filament-page-blocks.generator.namespace', 'App\\PageBlocks'), '\\');
        $classPath = ($package ? $root.'/src/Blocks' : (string) config('filament-page-blocks.generator.path', app_path('PageBlocks'))).'/'.$class.'.php';
        $viewPath = ($package ? $root.'/resources/views/blocks' : (string) config('filament-page-blocks.generator.view_path', resource_path('views/page-blocks'))).'/'.Str::kebab($base).'.blade.php';
        $view = $package ? 'filament-page-blocks::blocks.'.Str::kebab($base) : trim((string) config('filament-page-blocks.generator.view_namespace', 'page-blocks'), '.').'.'.Str::kebab($base);
        $targets = [$classPath, ...($withView ? [$viewPath] : [])];
        $providerPath = (string) config('filament-page-blocks.generator.provider_path', app_path('Providers/AppServiceProvider.php'));
        $fqcn = '\\'.$namespace.'\\'.$class;
        $providerContents = null;

        if (! $this->option('no-register')) {
            if (! $files->isFile($providerPath)) {
                $this->components->error("AppServiceProvider not found: {$providerPath}");

                return self::FAILURE;
            }

            try {
                $providerContents = $this->registerBlock($files->get($providerPath), $fqcn, $class);
            } catch (RuntimeException $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        if (! $this->option('force')) {
            foreach ($targets as $target) {
                if ($files->exists($target)) {
                    $this->components->error("File already exists: {$target}");

                    return self::FAILURE;
                }
            }
        }

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $class,
            '{{ identifier }}' => $identifier,
            '{{ label }}' => Str::headline($base),
            '{{ view }}' => $view,
        ];

        try {
            $this->write($files, $classPath, strtr($files->get($root.'/stubs/page-block.stub'), $replacements));
            $this->components->info("Created: {$classPath}");

            if ($withView) {
                $this->write($files, $viewPath, strtr($files->get($root.'/stubs/page-block-view.stub'), $replacements));
                $this->components->info("Created: {$viewPath}");
            }

            if ($providerContents !== null) {
                $files->put($providerPath, $providerContents, true);
                $this->components->info("Registered in: {$providerPath}");
            }
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($providerContents === null) {
            $this->newLine();
            $this->line('Automatic registration skipped. Register the block manually:');
            $this->line("PageBlocks::register([{$fqcn}::class]);");
        }

        return self::SUCCESS;
    }

    private function isPackageDevelopmentMode(): bool
    {
        $packageRoot = realpath(__DIR__.'/../../..');

        return $packageRoot !== false
            && is_file($packageRoot.'/.package-development');
    }

    private function write(Filesystem $files, string $path, string $contents): void
    {
        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents, true);
    }

    private function registerBlock(string $provider, string $fqcn, string $class): string
    {
        if (str_contains($provider, $fqcn.'::class') || preg_match('/\\b'.preg_quote($class, '/').'::class\\b/', $provider) === 1) {
            return $provider;
        }

        $pattern = '/(?<prefix>(?:PageBlocks|\\\\DannAPI\\\\FilamentPageBlocks\\\\Facades\\\\PageBlocks)::register\\s*\\(\\s*\\[)(?<body>.*?)(?<suffix>\\]\\s*\\);)/s';

        if (preg_match($pattern, $provider) === 1) {
            $updated = preg_replace_callback(
                $pattern,
                static function (array $matches) use ($fqcn): string {
                    $body = (string) $matches['body'];
                    $closingIndent = preg_match('/\\R([ \\t]*)$/', $body, $closingMatch) === 1
                        ? (string) $closingMatch[1]
                        : '        ';
                    $itemIndent = preg_match('/\\R([ \\t]+)\\S/', $body, $itemMatch) === 1
                        ? (string) $itemMatch[1]
                        : $closingIndent.'    ';
                    $body = rtrim($body);

                    return $matches['prefix']
                        .($body === '' ? PHP_EOL : $body.PHP_EOL)
                        .$itemIndent.$fqcn.'::class,'.PHP_EOL
                        .$closingIndent.$matches['suffix'];
                },
                $provider,
                1,
            );

            return is_string($updated)
                ? $updated
                : throw new RuntimeException('Unable to update AppServiceProvider registration.');
        }

        $bootPattern = '/(?<indent>^[ \\t]*)public function boot\\(\\): void\\s*\\{/m';

        if (preg_match($bootPattern, $provider) === 1) {
            $updated = preg_replace_callback(
                $bootPattern,
                static function (array $matches) use ($fqcn): string {
                    $bodyIndent = (string) $matches['indent'].'    ';

                    return $matches[0].PHP_EOL
                        .$bodyIndent.'\\DannAPI\\FilamentPageBlocks\\Facades\\PageBlocks::register(['.PHP_EOL
                        .$bodyIndent.'    '.$fqcn.'::class,'.PHP_EOL
                        .$bodyIndent.']);';
                },
                $provider,
                1,
            );

            return is_string($updated)
                ? $updated
                : throw new RuntimeException('Unable to update AppServiceProvider boot method.');
        }

        $lastBrace = strrpos($provider, '}');

        if ($lastBrace === false) {
            throw new RuntimeException('Unable to locate the AppServiceProvider class closing brace. Use --no-register and register the block manually.');
        }

        $method = PHP_EOL.'    public function boot(): void'.PHP_EOL.'    {'.PHP_EOL
            .'        \\DannAPI\\FilamentPageBlocks\\Facades\\PageBlocks::register(['.PHP_EOL
            .'            '.$fqcn.'::class,'.PHP_EOL
            .'        ]);'.PHP_EOL.'    }'.PHP_EOL;

        return substr($provider, 0, $lastBrace).$method.substr($provider, $lastBrace);
    }
}
