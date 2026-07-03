<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;

final class PublishUsersResourceCommand extends Command
{
    protected $signature = 'page-blocks:publish-users
        {--force : Overwrite previously published User Resource files}';

    protected $description = 'Publish the editable Filament Users Resource into the host application';

    public function handle(Filesystem $files): int
    {
        $packageRoot = dirname(__DIR__, 2);
        $resourceRoot = rtrim((string) config('filament-page-blocks.generator.admin_model.resource_path', app_path('Filament/Resources')), '/');
        $targetRoot = $resourceRoot.'/Users';
        $applicationNamespace = trim((string) config('filament-page-blocks.generator.admin_model.resource_namespace', 'App\\Filament\\Resources'), '\\').'\\Users';
        $packageNamespace = 'DannAPI\\FilamentPageBlocks\\Filament\\Resources';
        $targets = [
            "{$packageRoot}/src/Filament/Resources/UserResource.php" => "{$targetRoot}/UserResource.php",
            "{$packageRoot}/src/Filament/Resources/UserResource/Pages/ListUsers.php" => "{$targetRoot}/Pages/ListUsers.php",
            "{$packageRoot}/src/Filament/Resources/UserResource/Pages/CreateUser.php" => "{$targetRoot}/Pages/CreateUser.php",
            "{$packageRoot}/src/Filament/Resources/UserResource/Pages/EditUser.php" => "{$targetRoot}/Pages/EditUser.php",
        ];

        if (! $this->option('force')) {
            foreach ($targets as $target) {
                if ($files->exists($target)) {
                    $this->components->error("File already exists: {$target}");
                    $this->line('Use --force only when replacing intentional local customizations.');

                    return self::FAILURE;
                }
            }
        }

        $configPath = config_path('filament-page-blocks.php');
        try {
            $config = $files->isFile($configPath)
                ? $files->get($configPath)
                : $files->get("{$packageRoot}/config/filament-page-blocks.php");
            $config = $this->configureApplicationResource($config, $applicationNamespace.'\\UserResource');

            foreach ($targets as $source => $target) {
                if (! $files->isFile($source)) {
                    throw new RuntimeException("Package source file not found: {$source}");
                }

                $contents = $this->transformNamespace(
                    $files->get($source),
                    $packageNamespace,
                    $applicationNamespace,
                );
                $files->ensureDirectoryExists(dirname($target));
                $files->put($target, $contents, true);
                $this->components->info("Published: {$target}");
            }

            $files->put($configPath, $config, true);
            $this->components->info("Configured: {$configPath}");
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->callSilently('config:clear');
        $this->callSilently('filament:clear-cached-components');
        $this->newLine();
        $this->components->info('Users Resource published. Edit it under app/Filament/Resources/Users.');

        return self::SUCCESS;
    }

    private function transformNamespace(string $contents, string $packageNamespace, string $applicationNamespace): string
    {
        $contents = str_replace(
            "namespace {$packageNamespace};",
            "namespace {$applicationNamespace};",
            $contents,
        );
        $contents = str_replace(
            "namespace {$packageNamespace}\\UserResource\\Pages;",
            "namespace {$applicationNamespace}\\Pages;",
            $contents,
        );
        $contents = str_replace(
            "use {$packageNamespace}\\UserResource;",
            "use {$applicationNamespace}\\UserResource;",
            $contents,
        );
        $contents = str_replace(
            "use {$packageNamespace}\\UserResource\\Pages;",
            "use {$applicationNamespace}\\Pages;",
            $contents,
        );

        if (str_contains($contents, $packageNamespace.'\\UserResource')) {
            throw new RuntimeException('Unable to replace all package User Resource references.');
        }

        return $contents;
    }

    private function configureApplicationResource(string $config, string $resourceFqn): string
    {
        if (str_contains($config, "'users_resource' => \\{$resourceFqn}::class")) {
            return $config;
        }

        $updated = preg_replace(
            "/'users_resource'\s*=>\s*[^,]+,/", "'users_resource' => \\{$resourceFqn}::class,",
            $config,
            1,
            $count,
        );
        if (! is_string($updated) || $count !== 1) {
            throw new RuntimeException('Unable to update authorization.users_resource in config/filament-page-blocks.php.');
        }

        return $updated;
    }
}
