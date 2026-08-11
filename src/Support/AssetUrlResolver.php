<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Stringable;
use Throwable;

final class AssetUrlResolver
{
    public function resolve(mixed $source, mixed $fallback = null): ?string
    {
        $resolved = $this->resolveSource($source);

        return $resolved ?? $this->resolveSource($fallback);
    }

    public function accepts(mixed $source, ?string $type = null): bool
    {
        return $this->resolveSource($source, $type) !== null;
    }

    private function resolveSource(mixed $source, ?string $type = null): ?string
    {
        if ($source instanceof Stringable) {
            $source = (string) $source;
        }

        if (! is_string($source)) {
            return null;
        }

        $source = trim($source);
        if ($source === '' || $this->containsUnsafeCharacters($source)) {
            return null;
        }

        if ($this->isApplicationUrl($source)) {
            $path = (string) parse_url($source, PHP_URL_PATH);

            return $this->resolveLocalPath($path, $type);
        }

        if (filter_var($source, FILTER_VALIDATE_URL) !== false) {
            return $this->resolveRemoteUrl($source, $type);
        }

        if (str_starts_with($source, '//') || str_contains($source, '://')) {
            return null;
        }

        return $this->resolveLocalPath($source, $type);
    }

    private function resolveLocalPath(string $source, ?string $type): ?string
    {
        $path = rawurldecode((string) parse_url($source, PHP_URL_PATH));
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = ltrim($path, '/');
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if ($path === '' || $this->containsTraversal($path) || ! $this->hasAllowedExtension($path, $type)) {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $storagePath = substr($path, 8);
            if ($storagePath !== '' && $this->disk()->exists($storagePath)) {
                return $this->sanitizeUrl($this->absoluteStorageUrl($this->disk()->url($storagePath)));
            }

            return null;
        }

        $publicRoot = realpath(public_path());
        $publicFile = realpath(public_path($path));
        if (is_string($publicRoot) && is_string($publicFile) && $this->isWithin($publicFile, $publicRoot) && is_file($publicFile)) {
            return $this->sanitizeUrl(asset($path));
        }

        try {
            if ($this->disk()->exists($path)) {
                return $this->sanitizeUrl($this->absoluteStorageUrl($this->disk()->url($path)));
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function resolveRemoteUrl(string $source, ?string $type): ?string
    {
        if (! config('filament-page-blocks.media.asset_urls.remote_https_enabled', true)) {
            return null;
        }

        $parts = parse_url($source);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $port = (int) ($parts['port'] ?? 443);
        $allowedPorts = array_map('intval', (array) config('filament-page-blocks.media.asset_urls.remote_ports', [443]));
        if ($host === '' || isset($parts['user']) || isset($parts['pass']) || ! in_array($port, $allowedPorts, true)) {
            return null;
        }

        if ($this->isLocalOrReservedHost($host)) {
            return null;
        }

        $path = rawurldecode((string) ($parts['path'] ?? ''));
        if ($path === '' || str_contains($path, '\\') || $this->containsUnsafeCharacters($path) || $this->containsTraversal($path) || ! $this->hasAllowedExtension($path, $type)) {
            return null;
        }

        return $this->sanitizeUrl($source);
    }

    private function isApplicationUrl(string $source): bool
    {
        $sourceHost = strtolower((string) parse_url($source, PHP_URL_HOST));
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $sourceHost !== '' && $appHost !== '' && $sourceHost === $appHost;
    }

    private function isLocalOrReservedHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return true;
        }

        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function containsUnsafeCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    private function containsTraversal(string $path): bool
    {
        $segments = explode('/', str_replace('\\', '/', rawurldecode($path)));

        return in_array('..', $segments, true) || in_array('.', $segments, true);
    }

    private function hasAllowedExtension(string $path, ?string $type): bool
    {
        $extension = strtolower(pathinfo((string) parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION));
        if ($extension === '') {
            return false;
        }

        $groups = (array) config('filament-page-blocks.media.asset_urls.allowed_extensions', [
            'image' => ['jpg', 'jpeg', 'png', 'webp'],
            'video' => ['mp4', 'webm', 'mov'],
            'file' => ['pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx'],
        ]);
        $extensionGroups = array_values(array_filter($groups, 'is_array'));
        $allowed = $type === null
            ? ($extensionGroups === [] ? [] : array_merge(...$extensionGroups))
            : (array) ($groups[$type] ?? []);

        return in_array($extension, array_map(static fn (mixed $value): string => strtolower((string) $value), $allowed), true);
    }

    private function isWithin(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $root);
    }

    private function absoluteStorageUrl(string $url): string
    {
        return str_starts_with($url, '/') ? asset(ltrim($url, '/')) : $url;
    }

    private function sanitizeUrl(string $url): string
    {
        return str_replace(
            ['"', "'", '(', ')', '`', ' '],
            ['%22', '%27', '%28', '%29', '%60', '%20'],
            $url,
        );
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('filament-page-blocks.media.disk', 'public'));
    }
}
