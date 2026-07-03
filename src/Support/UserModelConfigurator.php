<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use DannAPI\FilamentPageBlocks\Models\Concerns\HasPageBlocksRoles;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use RuntimeException;

final readonly class UserModelConfigurator
{
    public function __construct(private Filesystem $files) {}

    /** @param class-string $model */
    public function configure(string $model): bool
    {
        if (class_exists($model, false) && is_a($model, FilamentUser::class, true) && method_exists($model, 'roles')) {
            return false;
        }

        $wasLoaded = class_exists($model, false);
        $path = $this->modelPath($model);
        if (! $this->files->isFile($path)) {
            throw new RuntimeException("Configured User model source was not found: {$path}");
        }

        $contents = $this->files->get($path);
        $basename = class_basename($model);
        $pattern = '/(?<header>\bclass\s+'.preg_quote($basename, '/').'\b[^\{]*)(?<brace>\{)/s';
        if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException("Unable to find class {$basename} in {$path}.");
        }

        $header = $match['header'][0];
        $headerOffset = $match['header'][1];
        $braceOffset = $match['brace'][1];
        [$originalBodyStart, $originalBodyEnd] = $this->classBody($contents, $braceOffset);
        $originalBody = substr($contents, $originalBodyStart, $originalBodyEnd - $originalBodyStart);
        $hasInterface = preg_match('/\bFilamentUser\b/', $header) === 1;
        $hasTrait = preg_match('/\b(?:HasPageBlocksRoles|'.preg_quote(HasPageBlocksRoles::class, '/').')\b/', $originalBody) === 1;
        if ($hasInterface && $hasTrait) {
            return false;
        }

        if (! $hasInterface) {
            $trailingWhitespace = substr($header, strlen(rtrim($header)));
            $header = (preg_match('/\bimplements\b/', $header) === 1
                ? rtrim($header).', FilamentUser'
                : rtrim($header).' implements FilamentUser')
                .($trailingWhitespace !== '' ? $trailingWhitespace : ' ');
        }

        $contents = substr($contents, 0, $headerOffset).$header.substr($contents, $braceOffset);
        $braceOffset = $headerOffset + strlen($header);
        [$bodyStart, $bodyEnd] = $this->classBody($contents, $braceOffset);
        $body = substr($contents, $bodyStart, $bodyEnd - $bodyStart);

        if (! $hasTrait) {
            $classIndent = $this->lineIndentAt($contents, $braceOffset);
            $body = "\n{$classIndent}    use HasPageBlocksRoles;\n".ltrim($body, "\r\n");
            $contents = substr($contents, 0, $bodyStart).$body.substr($contents, $bodyEnd);
        }

        $contents = $this->normalizeUse($contents, FilamentUser::class);
        $contents = $this->normalizeUse($contents, HasPageBlocksRoles::class);
        $this->files->put($path, $contents, true);

        if ($wasLoaded) {
            throw new RuntimeException("User model was configured in {$path}, but its old class is already loaded. Rerun page-blocks:install once.");
        }

        return true;
    }

    /** @param class-string $model */
    private function modelPath(string $model): string
    {
        $namespace = app()->getNamespace();
        if (str_starts_with($model, $namespace)) {
            $relative = str_replace('\\', '/', substr($model, strlen($namespace)));

            return app_path($relative.'.php');
        }

        if (class_exists($model)) {
            $path = (new ReflectionClass($model))->getFileName();
            if (is_string($path)) {
                return $path;
            }
        }

        throw new RuntimeException("Cannot determine the source path for configured User model [{$model}].");
    }

    private function normalizeUse(string $contents, string $class): string
    {
        $pattern = '~^use\s+'.preg_quote($class, '~').'\s*;[\t ]*\R~mi';
        $contents = preg_replace($pattern, '', $contents);
        if (! is_string($contents)) {
            throw new RuntimeException("Unable to normalize import [{$class}].");
        }

        $namespacePosition = strpos($contents, 'namespace ');
        $namespaceEnd = $namespacePosition === false ? false : strpos($contents, ';', $namespacePosition);
        if ($namespaceEnd === false) {
            throw new RuntimeException('Unable to find the User model namespace.');
        }

        return substr($contents, 0, $namespaceEnd + 1)
            ."\n\nuse {$class};\n"
            .ltrim(substr($contents, $namespaceEnd + 1), "\r\n");
    }

    /** @return array{int, int} */
    private function classBody(string $contents, int $braceOffset): array
    {
        $tokens = token_get_all($contents);
        $offset = 0;
        $depth = 0;
        $started = false;

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;
            if ($offset === $braceOffset && $text === '{') {
                $started = true;
                $depth = 1;
            } elseif ($started) {
                if ($text === '{') {
                    $depth++;
                } elseif ($text === '}' && --$depth === 0) {
                    return [$braceOffset + 1, $offset];
                }
            }
            $offset += strlen($text);
        }

        throw new RuntimeException('Unable to parse the User model class body.');
    }

    private function lineIndentAt(string $contents, int $offset): string
    {
        $lineStart = strrpos(substr($contents, 0, $offset), "\n");
        $line = substr($contents, $lineStart === false ? 0 : $lineStart + 1, $offset - ($lineStart === false ? 0 : $lineStart + 1));

        return preg_match('/^[\t ]*/', $line, $matches) === 1 ? $matches[0] : '';
    }
}
