<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class PanelProviderConfigurator
{
    private const string PLUGIN_IMPORT = 'DannAPI\\FilamentPageBlocks\\Filament\\FilamentPageBlocksPlugin';

    private const string INFO_WIDGET_IMPORT = 'Filament\\Widgets\\FilamentInfoWidget';

    public function __construct(private Filesystem $files) {}

    /** @return array{class: class-string<PanelProvider>, path: string, id: string, panel_path: string} */
    public function first(): ?array
    {
        foreach ($this->files->allFiles(app_path('Providers')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->className($this->files->get($file->getPathname()));
            if ($class === null || ! class_exists($class) || ! is_subclass_of($class, PanelProvider::class)) {
                continue;
            }

            /** @var class-string<PanelProvider> $class */
            $provider = new $class(app());
            $panel = $provider->panel(Panel::make());

            return [
                'class' => $class,
                'path' => $file->getPathname(),
                'id' => $panel->getId(),
                'panel_path' => $panel->getPath(),
            ];
        }

        return null;
    }

    /** @return array{plugin_added: bool, info_widget_removed: bool, path_changed: bool} */
    public function configure(string $path, ?string $panelPath = null): array
    {
        if (! $this->files->isFile($path)) {
            throw new RuntimeException("Panel provider not found: {$path}");
        }

        $contents = $this->files->get($path);
        [$bodyStart, $bodyEnd] = $this->methodBody($contents, 'panel');
        $body = substr($contents, $bodyStart, $bodyEnd - $bodyStart);
        $pluginAdded = false;
        $infoWidgetRemoved = false;
        $pathChanged = false;

        $body = preg_replace(
            '/[\t ]*FilamentInfoWidget\s*::\s*class\s*,?[\t ]*(?:\R)?/',
            '',
            $body,
            -1,
            $infoWidgetCount,
        );
        if (! is_string($body)) {
            throw new RuntimeException('Unable to remove FilamentInfoWidget.');
        }
        $infoWidgetRemoved = $infoWidgetCount > 0;

        if ($panelPath !== null) {
            $normalizedPath = trim($panelPath, '/');
            $replacement = "->path('".str_replace("'", "\\'", $normalizedPath)."')";
            $body = preg_replace('/->path\s*\(\s*([\'\"])[^\'\"]*\1\s*\)/', $replacement, $body, 1, $pathCount);
            if (! is_string($body) || $pathCount !== 1) {
                throw new RuntimeException('Unable to configure the generated panel path.');
            }
            $pathChanged = true;
        }

        if (! str_contains($body, 'FilamentPageBlocksPlugin::make(') && ! str_contains($body, self::PLUGIN_IMPORT.'::make(')) {
            $body = $this->addPlugin($body);
            $pluginAdded = true;
        }

        $updated = substr($contents, 0, $bodyStart).$body.substr($contents, $bodyEnd);
        if (substr_count($updated, 'FilamentInfoWidget') <= 1) {
            $updated = $this->normalizeUse($updated, self::INFO_WIDGET_IMPORT, remove: true);
        }
        $updated = $this->normalizeUse($updated, self::PLUGIN_IMPORT, remove: false);

        if ($updated !== $contents) {
            $this->files->put($path, $updated, true);
        }

        return [
            'plugin_added' => $pluginAdded,
            'info_widget_removed' => $infoWidgetRemoved,
            'path_changed' => $pathChanged,
        ];
    }

    private function addPlugin(string $body): string
    {
        $pluginsPosition = strpos($body, '->plugins');
        if ($pluginsPosition !== false) {
            $openParenthesis = strpos($body, '(', $pluginsPosition);
            $openBracket = $openParenthesis === false ? false : strpos($body, '[', $openParenthesis);
            if ($openBracket === false) {
                throw new RuntimeException('The existing panel plugins() call must receive an array.');
            }

            $closeBracket = $this->matchingDelimiter($body, $openBracket, '[', ']');
            $indent = $this->lineIndentAt($body, $closeBracket).'    ';
            $insertion = "{$indent}FilamentPageBlocksPlugin::make(),\n";
            $lineStart = strrpos(substr($body, 0, $closeBracket), "\n");
            $insertionPosition = $lineStart === false ? $closeBracket : $lineStart + 1;

            return substr($body, 0, $insertionPosition).$insertion.substr($body, $insertionPosition);
        }

        $semicolon = strrpos($body, ';');
        if ($semicolon === false) {
            throw new RuntimeException('Unable to find the end of the panel return chain.');
        }
        $indent = $this->chainIndent($body);
        $insertion = "\n{$indent}->plugins([\n{$indent}    FilamentPageBlocksPlugin::make(),\n{$indent}])";

        return substr($body, 0, $semicolon).$insertion.substr($body, $semicolon);
    }

    private function normalizeUse(string $contents, string $class, bool $remove): string
    {
        $pattern = '~^use\s+'.preg_quote($class, '~').'\s*;[\t ]*\R~mi';
        $contents = preg_replace($pattern, '', $contents);
        if (! is_string($contents) || $remove) {
            return $contents;
        }

        $namespaceEnd = strpos($contents, ';', strpos($contents, 'namespace '));
        if ($namespaceEnd === false) {
            throw new RuntimeException('Unable to find the panel provider namespace.');
        }

        return substr($contents, 0, $namespaceEnd + 1)
            ."\n\nuse {$class};\n"
            .ltrim(substr($contents, $namespaceEnd + 1), "\r\n");
    }

    /** @return array{int, int} */
    private function methodBody(string $contents, string $method): array
    {
        $tokens = token_get_all($contents);
        $offset = 0;
        $waitingForName = false;
        $waitingForBody = false;
        $bodyStart = null;
        $depth = 0;

        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $id = is_array($token) ? $token[0] : null;
            if ($id === T_FUNCTION) {
                $waitingForName = true;
                $waitingForBody = false;
            } elseif ($waitingForName && $id === T_STRING) {
                $waitingForName = false;
                $waitingForBody = strcasecmp($text, $method) === 0;
            } elseif ($waitingForBody && $text === '{') {
                $bodyStart = $offset + 1;
                $depth = 1;
                $waitingForBody = false;
            } elseif ($bodyStart !== null) {
                $depth += $text === '{' ? 1 : ($text === '}' ? -1 : 0);
                if ($depth === 0) {
                    return [$bodyStart, $offset];
                }
            }
            $offset += strlen($text);
        }

        throw new RuntimeException("Unable to find {$method}() method body.");
    }

    private function matchingDelimiter(string $contents, int $start, string $open, string $close): int
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
            } elseif ($character === $open) {
                $depth++;
            } elseif ($character === $close && --$depth === 0) {
                return $index;
            }
        }

        throw new RuntimeException('Unable to parse the existing plugins() array.');
    }

    private function chainIndent(string $contents): string
    {
        if (preg_match('/^(?<indent>[\t ]*)->/m', $contents, $matches) === 1) {
            return $matches['indent'];
        }

        return '            ';
    }

    private function lineIndentAt(string $contents, int $offset): string
    {
        $lineStart = strrpos(substr($contents, 0, $offset), "\n");
        $line = substr($contents, $lineStart === false ? 0 : $lineStart + 1, $offset - ($lineStart === false ? 0 : $lineStart + 1));

        return preg_match('/^[\t ]*/', $line, $matches) === 1 ? $matches[0] : '';
    }

    /** @return class-string|null */
    private function className(string $contents): ?string
    {
        if (preg_match('/namespace\s+([^;]+)\s*;/', $contents, $namespace) !== 1
            || preg_match('/\bclass\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $contents, $class) !== 1) {
            return null;
        }

        return trim($namespace[1]).'\\'.$class[1];
    }
}
