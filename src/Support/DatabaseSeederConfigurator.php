<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class DatabaseSeederConfigurator
{
    private const string PACKAGE_SEEDER = 'DannAPI\\FilamentPageBlocks\\Database\\Seeders\\FilamentPageBlocksSeeder';

    private const string EXAMPLE_PAGE_SEEDER = 'ExamplePageSeeder';

    public function __construct(private Filesystem $files) {}

    public function configure(string $path, bool $includeExamplePage = true): bool
    {
        if (! $this->files->isFile($path)) {
            throw new RuntimeException("DatabaseSeeder not found: {$path}");
        }

        $contents = $this->files->get($path);
        [$bodyStart, $bodyEnd] = $this->runMethodBody($contents);
        $body = substr($contents, $bodyStart, $bodyEnd - $bodyStart);

        $body = $this->removeManagedSeederCalls($body);
        $body = $this->removeDefaultLaravelFactoryExample($body);

        $methodIndent = $this->lineIndentAt($contents, $bodyStart - 1);
        $indent = $methodIndent.'    ';
        $examplePageCall = $includeExamplePage ? "\n{$indent}    ExamplePageSeeder::class," : '';
        $call = "\n{$indent}\$this->call([\n{$indent}    FilamentPageBlocksSeeder::class,{$examplePageCall}\n{$indent}]);\n";
        $body = $call.ltrim($body, "\r\n");
        $updated = substr($contents, 0, $bodyStart).$body.substr($contents, $bodyEnd);
        $updated = $this->normalizeImports($updated, str_contains($body, 'User::'));

        if ($updated === $contents) {
            return false;
        }

        $this->files->put($path, $updated, true);

        return true;
    }

    /** @return array{int, int} */
    private function runMethodBody(string $contents): array
    {
        $tokens = token_get_all($contents);
        $offset = 0;
        $waitingForName = false;
        $isRun = false;
        $waitingForBody = false;
        $bodyStart = null;
        $depth = 0;

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $id = is_array($token) ? $token[0] : null;

            if ($id === T_FUNCTION) {
                $waitingForName = true;
                $isRun = false;
            } elseif ($waitingForName && $id === T_STRING) {
                $isRun = strcasecmp($text, 'run') === 0;
                $waitingForName = false;
                $waitingForBody = $isRun;
            } elseif ($waitingForBody && $text === '{') {
                $bodyStart = $offset + 1;
                $depth = 1;
                $waitingForBody = false;
            } elseif ($bodyStart !== null) {
                if ($text === '{') {
                    $depth++;
                } elseif ($text === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return [$bodyStart, $offset];
                    }
                }
            }

            $offset += strlen($text);
        }

        throw new RuntimeException('Unable to find DatabaseSeeder::run() method body.');
    }

    private function normalizeImports(string $contents, bool $usesUserModel): string
    {
        $pattern = '~^use\s+'.preg_quote(self::PACKAGE_SEEDER, '~').'\s*;[\t ]*\R~mi';
        $contents = preg_replace($pattern, '', $contents);
        if (! is_string($contents)) {
            throw new RuntimeException('Unable to normalize the package seeder import.');
        }

        if (! $usesUserModel) {
            $userImportPattern = '~^use\s+'.preg_quote('App\\Models\\User', '~').'\s*;[\t ]*\R~mi';
            $contents = preg_replace($userImportPattern, '', $contents);
            if (! is_string($contents)) {
                throw new RuntimeException('Unable to remove the unused default User model import.');
            }
        }

        $namespaceEnd = strpos($contents, ';', strpos($contents, 'namespace '));
        if ($namespaceEnd === false) {
            throw new RuntimeException('Unable to find the DatabaseSeeder namespace.');
        }

        return substr($contents, 0, $namespaceEnd + 1)
            ."\n\nuse ".self::PACKAGE_SEEDER.";\n"
            .ltrim(substr($contents, $namespaceEnd + 1), "\r\n");
    }

    private function removeManagedSeederCalls(string $body): string
    {
        preg_match_all('/\$this\s*->\s*call\s*\(/i', $body, $matches, PREG_OFFSET_CAPTURE);
        $calls = $matches[0] ?? [];

        foreach (array_reverse($calls) as $match) {
            [$expression, $start] = $match;
            $open = $start + strlen($expression) - 1;
            $close = $this->matchingParenthesis($body, $open);
            $semicolon = $close + 1;
            while (isset($body[$semicolon]) && ctype_space($body[$semicolon])) {
                $semicolon++;
            }
            if (($body[$semicolon] ?? null) !== ';') {
                continue;
            }

            $arguments = substr($body, $open + 1, $close - $open - 1);
            $normalized = $this->removeClassReferences($arguments, [
                self::PACKAGE_SEEDER,
                'Database\\Seeders\\FilamentPageBlocksSeeder',
                'FilamentPageBlocksSeeder',
                'Database\\Seeders\\GeneralInfoSeeder',
                'GeneralInfoSeeder',
                'Database\\Seeders\\'.self::EXAMPLE_PAGE_SEEDER,
                self::EXAMPLE_PAGE_SEEDER,
            ]);
            if ($normalized === $arguments) {
                continue;
            }

            $compact = preg_replace('/\s+/', '', $normalized);
            $isEmpty = in_array($compact, ['', '[]'], true);
            $replacement = $isEmpty ? '' : substr($body, $start, $open - $start + 1).$normalized.');';
            $replaceStart = $start;
            $replaceEnd = $semicolon + 1;
            if ($isEmpty) {
                $lineStart = strrpos(substr($body, 0, $start), "\n");
                $lineStart = $lineStart === false ? 0 : $lineStart + 1;
                if (trim(substr($body, $lineStart, $start - $lineStart)) === '') {
                    $replaceStart = $lineStart;
                    if (substr($body, $replaceEnd, 2) === "\r\n") {
                        $replaceEnd += 2;
                    } elseif (($body[$replaceEnd] ?? null) === "\n") {
                        $replaceEnd++;
                    }
                }
            }
            $body = substr($body, 0, $replaceStart).$replacement.substr($body, $replaceEnd);
        }

        return $body;
    }

    private function removeDefaultLaravelFactoryExample(string $body): string
    {
        $body = preg_replace(
            '~^[\t ]*//[\t ]*User::factory\(10\)->create\(\);[\t ]*\R~m',
            '',
            $body,
        );
        if (! is_string($body)) {
            throw new RuntimeException('Unable to remove the default Laravel factory comment.');
        }

        $factoryPattern = <<<'REGEX'
~^[\t ]*User::factory\(\)->create\(\s*\[\s*['"]name['"]\s*=>\s*['"]Test User['"]\s*,\s*['"]email['"]\s*=>\s*['"]test@example\.com['"]\s*,?\s*\]\s*\);[\t ]*(?:\R|$)~m
REGEX;
        $body = preg_replace($factoryPattern, '', $body);
        if (! is_string($body)) {
            throw new RuntimeException('Unable to remove the default Laravel example user factory.');
        }

        return $body;
    }

    /** @param array<string> $classes */
    private function removeClassReferences(string $arguments, array $classes): string
    {
        $classes = array_map(static fn (string $class): string => preg_quote($class, '~'), $classes);
        $reference = '(?:\\\\)?(?:'.implode('|', $classes).')\s*::\s*class';
        $updated = preg_replace([
            '~'.$reference.'\s*,\s*~i',
            '~,\s*'.$reference.'~i',
            '~'.$reference.'~i',
        ], '', $arguments);

        if (! is_string($updated)) {
            throw new RuntimeException('Unable to normalize DatabaseSeeder calls.');
        }

        return $updated;
    }

    private function matchingParenthesis(string $contents, int $start): int
    {
        $depth = 0;
        $quote = null;
        $escaped = false;
        for ($index = $start, $length = strlen($contents); $index < $length; $index++) {
            $character = $contents[$index];
            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')' && --$depth === 0) {
                return $index;
            }
        }

        throw new RuntimeException('Unable to parse a DatabaseSeeder call expression.');
    }

    private function lineIndentAt(string $contents, int $offset): string
    {
        $lineStart = strrpos(substr($contents, 0, max(0, $offset)), "\n");
        $line = substr($contents, $lineStart === false ? 0 : $lineStart + 1, $offset - ($lineStart === false ? 0 : $lineStart + 1));

        return preg_match('/^[\t ]*/', $line, $matches) === 1 ? $matches[0] : '';
    }
}
