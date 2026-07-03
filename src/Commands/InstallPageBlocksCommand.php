<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Commands;

use DannAPI\FilamentPageBlocks\Database\Seeders\FilamentPageBlocksSeeder;
use DannAPI\FilamentPageBlocks\Support\DatabaseSeederConfigurator;
use DannAPI\FilamentPageBlocks\Support\PanelProviderConfigurator;
use DannAPI\FilamentPageBlocks\Support\UserModelConfigurator;
use Filament\Models\Contracts\FilamentUser;
use Filament\PanelProvider;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

final class InstallPageBlocksCommand extends Command
{
    protected $signature = 'page-blocks:install
        {--force : Overwrite published package files and allow production installation}
        {--skip-migrate : Publish files without running migrations}
        {--skip-seed : Do not run package seeders}
        {--skip-composer : Do not add Filament as a direct root Composer dependency}
        {--without-example-page : Do not publish, register, or seed ExamplePageSeeder}
        {--panel-id= : ID used when a Filament panel must be created}
        {--panel-path= : URL path used when a Filament panel must be created}';

    protected $description = 'Install Filament Page Blocks and publish editable application scaffolding';

    public function handle(
        Filesystem $files,
        DatabaseSeederConfigurator $databaseSeeder,
        PanelProviderConfigurator $panelProvider,
        UserModelConfigurator $userModel,
    ): int {
        $force = (bool) $this->option('force');
        if (app()->environment('production') && ! $force) {
            $this->components->error('Use --force to run the installer in production.');

            return self::FAILURE;
        }

        $packageRoot = dirname(__DIR__, 2);
        $includeExamplePage = $this->shouldInstallExamplePage();

        try {
            $this->installFilamentComposerDependency($files);
            $this->publishConfig($files, $packageRoot, $force);
            $this->publishMigrations($files, $packageRoot, $force);
            $this->publishApplicationScaffold($files, $packageRoot, $force, $includeExamplePage);
            $this->configureDatabaseSeeder($databaseSeeder, $includeExamplePage);
            $this->configureUserModel($userModel);
            $panel = $this->configurePanel($panelProvider);
            $this->installFilamentAssets();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->callSilently('config:clear');
        $this->callSilently('filament:clear-cached-components');

        if (! $this->option('skip-seed') && ! $this->userModelSupportsPackageRoles()) {
            return self::FAILURE;
        }

        if (! $this->option('skip-migrate')) {
            $arguments = $force ? ['--force' => true] : [];
            if ($this->call('migrate', $arguments) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (! $this->option('skip-seed')) {
            if ($this->option('skip-migrate')) {
                $this->components->warn('Seeding was skipped because --skip-migrate was used. Run migrations before seeding.');
            } else {
                $arguments = ['--class' => FilamentPageBlocksSeeder::class];
                if ($force) {
                    $arguments['--force'] = true;
                }
                if ($this->call('db:seed', $arguments) !== self::SUCCESS) {
                    return self::FAILURE;
                }

                if ($includeExamplePage) {
                    $arguments = ['--class' => 'Database\\Seeders\\ExamplePageSeeder'];
                    if ($force) {
                        $arguments['--force'] = true;
                    }
                    if ($this->call('db:seed', $arguments) !== self::SUCCESS) {
                        return self::FAILURE;
                    }
                }
            }
        }

        $this->newLine();
        $this->components->info('Filament Page Blocks installed. GeneralInfo is available at /admin/general-info.');
        $this->line("Panel provider: {$panel['class']} ({$panel['path']})");
        $this->line("Panel ID: {$panel['id']}");
        $this->line("Panel path: /{$panel['panel_path']}");
        $this->line('Plugin: '.($panel['plugin_added'] ? 'added' : 'already configured'));

        return self::SUCCESS;
    }

    private function publishConfig(Filesystem $files, string $packageRoot, bool $force): void
    {
        $source = "{$packageRoot}/config/filament-page-blocks.php";
        $target = config_path('filament-page-blocks.php');
        $this->publishFile($files, $source, $target, $force);

        $config = $files->get($target);
        $configured = preg_replace(
            "/('models'\s*=>\s*\[.*?'general_info'\s*=>\s*)[^,]+,/s",
            '$1\\App\\Models\\GeneralInfo::class,',
            $config,
            1,
            $modelCount,
        );
        if (! is_string($configured)) {
            throw new RuntimeException('Unable to configure models.general_info.');
        }
        if ($modelCount === 0) {
            $configured = preg_replace(
                "/('page_block'\s*=>\s*[^,]+,)/",
                "$1\n        'general_info' => \\App\\Models\\GeneralInfo::class,",
                $configured,
                1,
                $modelCount,
            );
            if (! is_string($configured) || $modelCount !== 1) {
                throw new RuntimeException('Unable to add models.general_info to the published config.');
            }
        }

        if (! str_contains($configured, "'general_info' => 'general_info'")) {
            $configured = preg_replace(
                "/('page_blocks'\s*=>\s*'[^']+',)/",
                "$1\n        'general_info' => 'general_info',",
                $configured,
                1,
            );
        }

        $configured = preg_replace(
            '/^use (?:App|DannAPI\\\\FilamentPageBlocks)\\\\Models\\\\GeneralInfo;\R/m',
            '',
            $configured,
        );
        if (! is_string($configured)) {
            throw new RuntimeException('Unable to normalize the GeneralInfo config import.');
        }

        $files->put($target, $configured, true);
        $this->components->info("Configured: {$target}");
    }

    private function publishMigrations(Filesystem $files, string $packageRoot, bool $force): void
    {
        foreach ($files->files("{$packageRoot}/database/migrations") as $migration) {
            $this->publishFile(
                $files,
                $migration->getPathname(),
                database_path('migrations/'.$migration->getFilename()),
                $force,
            );
        }
    }

    private function publishApplicationScaffold(
        Filesystem $files,
        string $packageRoot,
        bool $force,
        bool $includeExamplePage,
    ): void {
        $this->publishFile($files, "{$packageRoot}/stubs/general-info-model.stub", app_path('Models/GeneralInfo.php'), $force);
        $this->publishFile($files, "{$packageRoot}/stubs/general-info-seeder.stub", database_path('seeders/GeneralInfoSeeder.php'), $force);
        $this->publishFile(
            $files,
            "{$packageRoot}/stubs/general-info-permissions.stub",
            app_path('Filament/Permissions/general_info.php'),
            $force,
        );
        if ($includeExamplePage) {
            $this->publishFile(
                $files,
                "{$packageRoot}/stubs/example-page-seeder.stub",
                database_path('seeders/ExamplePageSeeder.php'),
                $force,
            );
        }
    }

    private function configureDatabaseSeeder(DatabaseSeederConfigurator $configurator, bool $includeExamplePage): void
    {
        $path = database_path('seeders/DatabaseSeeder.php');
        if (! $configurator->configure($path, $includeExamplePage)) {
            $this->components->info("Already configured: {$path}");

            return;
        }
        $this->components->info("Configured: {$path}");
    }

    private function shouldInstallExamplePage(): bool
    {
        if ($this->option('without-example-page')) {
            return false;
        }

        if (! $this->input->isInteractive()) {
            return true;
        }

        return $this->confirm('Publish and seed an editable example page?', true);
    }

    private function configureUserModel(UserModelConfigurator $configurator): void
    {
        $model = config('filament-page-blocks.authorization.user_model', 'App\\Models\\User');
        if (! is_string($model) || $model === '') {
            throw new RuntimeException('authorization.user_model must be a User model class-string.');
        }

        if ($configurator->configure($model)) {
            $this->components->info("Configured Filament access and package roles on User model: {$model}");

            return;
        }

        $this->components->info("Already configured User model: {$model}");
    }

    /**
     * @return array{
     *     class: class-string<PanelProvider>,
     *     path: string,
     *     id: string,
     *     panel_path: string,
     *     plugin_added: bool
     * }
     */
    private function configurePanel(PanelProviderConfigurator $configurator): array
    {
        $panel = $configurator->first();
        $requestedPath = null;

        if ($panel === null) {
            $id = $this->panelId();
            $requestedPath = $this->panelPath($id);
            $exitCode = $this->call('make:filament-panel', [
                'id' => $id,
                '--no-interaction' => true,
            ]);
            if ($exitCode !== self::SUCCESS) {
                throw new RuntimeException('Filament could not create the panel provider.');
            }

            $panel = $configurator->first();
        }

        if ($panel === null) {
            throw new RuntimeException('No Filament PanelProvider could be found after panel installation.');
        }

        $changes = $configurator->configure($panel['path'], $requestedPath);
        if ($changes['info_widget_removed']) {
            $this->components->info('Removed FilamentInfoWidget from the panel dashboard.');
        }

        if ($requestedPath !== null) {
            $panel['panel_path'] = trim($requestedPath, '/');
        }

        return [...$panel, 'plugin_added' => $changes['plugin_added']];
    }

    private function panelId(): string
    {
        $id = $this->option('panel-id');
        if (! is_string($id) || trim($id) === '') {
            $id = $this->input->isInteractive()
                ? (string) $this->ask('Filament panel ID', 'admin')
                : 'admin';
        }
        $id = trim($id);

        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $id) !== 1) {
            throw new RuntimeException('The panel ID must start with a letter and contain only letters, numbers, dashes, or underscores.');
        }

        return $id;
    }

    private function installFilamentAssets(): void
    {
        $exitCode = $this->call('filament:install', [
            '--no-interaction' => true,
        ]);
        if ($exitCode !== self::SUCCESS) {
            throw new RuntimeException('Filament installation could not publish its frontend assets.');
        }

        $this->components->info('Filament assets and the post-autoload upgrade hook are configured.');
    }

    private function installFilamentComposerDependency(Filesystem $files): void
    {
        if ($this->option('skip-composer')) {
            $this->components->warn('Skipped direct Filament Composer dependency configuration.');

            return;
        }

        $composerJsonPath = base_path('composer.json');
        if (! $files->isFile($composerJsonPath)) {
            throw new RuntimeException("Root composer.json not found: {$composerJsonPath}");
        }

        try {
            $composerJson = json_decode($files->get($composerJsonPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('Root composer.json is not valid JSON.', previous: $exception);
        }

        if (is_array($composerJson) && isset($composerJson['require']['filament/filament'])) {
            $this->components->info('Already configured Composer dependency: filament/filament.');

            return;
        }

        $composer = (new ExecutableFinder)->find('composer');
        if (! is_string($composer) || $composer === '') {
            throw new RuntimeException('Composer executable was not found in PATH. Use --skip-composer only when the root dependency is managed separately.');
        }

        $command = [$composer];
        $firstLine = $files->isFile($composer) ? (string) strtok($files->get($composer), "\r\n") : '';
        if (str_ends_with(strtolower($composer), '.phar') || str_contains(strtolower($firstLine), 'php')) {
            array_unshift($command, PHP_BINARY);
        }
        array_push(
            $command,
            'require',
            'filament/filament:^5.0',
            '--no-interaction',
            '--no-progress',
            '--minimal-changes',
        );

        $this->components->info('Adding filament/filament to the root Composer requirements...');
        $process = new Process($command, base_path(), timeout: null);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Composer could not add filament/filament to the root project: '.trim($process->getErrorOutput()));
        }

        $this->components->info('Configured root Composer dependency: filament/filament:^5.0.');
    }

    private function panelPath(string $id): string
    {
        $path = $this->option('panel-path');
        if (! is_string($path) || trim($path) === '') {
            $path = $this->input->isInteractive()
                ? (string) $this->ask('Filament panel path', $id)
                : $id;
        }
        $path = trim($path, " \t\n\r\0\x0B/");

        if ($path === '' || preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$~', $path) !== 1) {
            throw new RuntimeException('The panel path must contain URL-safe path segments.');
        }

        return $path;
    }

    private function userModelSupportsPackageRoles(): bool
    {
        $model = config('filament-page-blocks.authorization.user_model', 'App\\Models\\User');
        if (
            is_string($model)
            && class_exists($model)
            && is_a($model, FilamentUser::class, true)
            && method_exists($model, 'roles')
        ) {
            return true;
        }

        $this->components->error('The configured User model must implement FilamentUser and use HasPageBlocksRoles before initial seeding.');
        $this->line('Update App\\Models\\User as documented, then rerun [php artisan page-blocks:install].');

        return false;
    }

    private function publishFile(Filesystem $files, string $source, string $target, bool $force): void
    {
        if (! $files->isFile($source)) {
            throw new RuntimeException("Package file not found: {$source}");
        }
        if ($files->exists($target) && ! $force) {
            $this->components->info("Preserved existing: {$target}");

            return;
        }

        $files->ensureDirectoryExists(dirname($target));
        if (! $files->copy($source, $target)) {
            throw new RuntimeException("Unable to publish: {$target}");
        }
        $this->components->info("Published: {$target}");
    }
}
