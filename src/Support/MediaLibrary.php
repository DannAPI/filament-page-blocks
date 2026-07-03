<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class MediaLibrary
{
    /** @return array<string, array<string, mixed>> */
    public function collections(): array
    {
        return array_filter(
            (array) config('filament-page-blocks.media.library.collections', []),
            static fn (mixed $collection): bool => is_array($collection),
        );
    }

    /** @return array<int, array{name: string, path: string, type: string, mime_type: ?string, url: ?string, size: ?int, last_modified: ?int, writable: bool}> */
    public function entries(string $collection, string $path = ''): array
    {
        $definition = $this->definition($collection);
        $path = $this->allowedPath($definition, $path);
        $filesystem = $this->filesystem($definition);
        $directory = $this->fullPath($definition, $path);
        $writable = (bool) ($definition['writable'] ?? false);
        $entries = [];

        foreach ($filesystem->directories($directory) as $folder) {
            $relative = $this->relativePath($definition, $folder);
            if ($this->isExcluded($definition, $relative)) {
                continue;
            }
            $entries[] = [
                'name' => basename($folder),
                'path' => $relative,
                'type' => 'folder',
                'mime_type' => null,
                'url' => null,
                'size' => null,
                'last_modified' => null,
                'writable' => $writable,
            ];
        }

        foreach ($filesystem->files($directory) as $file) {
            $relative = $this->relativePath($definition, $file);
            $media = $this->mediaType($filesystem, $file);
            if ($this->isExcluded($definition, $relative) || $media === null) {
                continue;
            }

            $entries[] = [
                'name' => basename($file),
                'path' => $relative,
                'type' => $media['type'],
                'mime_type' => $media['mime_type'],
                'url' => $filesystem->url($file),
                'size' => $filesystem->size($file),
                'last_modified' => $filesystem->lastModified($file),
                'writable' => $writable,
            ];
        }

        usort($entries, static fn (array $left, array $right): int => [$left['type'] !== 'folder', strtolower($left['name'])] <=> [$right['type'] !== 'folder', strtolower($right['name'])]);

        return $entries;
    }

    /** @return array{name: string, path: string, type: string, mime_type: string, url: string, size: int, last_modified: int, writable: bool} */
    public function entry(string $collection, string $path): array
    {
        $definition = $this->definition($collection);
        $relative = $this->allowedPath($definition, $path);
        $filesystem = $this->filesystem($definition);
        $file = $this->fullPath($definition, $relative);
        $media = $filesystem->exists($file) ? $this->mediaType($filesystem, $file) : null;

        if ($media === null) {
            throw new InvalidArgumentException('Media file does not exist or its type is not supported.');
        }

        return [
            'name' => basename($file),
            'path' => $relative,
            'type' => $media['type'],
            'mime_type' => $media['mime_type'],
            'url' => $filesystem->url($file),
            'size' => $filesystem->size($file),
            'last_modified' => $filesystem->lastModified($file),
            'writable' => (bool) ($definition['writable'] ?? false),
        ];
    }

    public function isWritable(string $collection): bool
    {
        return (bool) ($this->definition($collection)['writable'] ?? false);
    }

    public function path(string $collection, string $path): string
    {
        return $this->allowedPath($this->definition($collection), $path);
    }

    public function upload(string $collection, string $path, UploadedFile $file): string
    {
        $definition = $this->writableDefinition($collection);
        $mimeType = (string) $file->getMimeType();
        $imageMimeTypes = (array) config('filament-page-blocks.media.image_mime_types', []);
        $videoMimeTypes = (array) config('filament-page-blocks.media.video_mime_types', []);
        $allowedMimeTypes = array_values(array_unique([...$imageMimeTypes, ...$videoMimeTypes]));
        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            throw new InvalidArgumentException("Media MIME type [{$mimeType}] is not allowed.");
        }
        $isVideo = in_array($mimeType, $videoMimeTypes, true);
        $maxSize = $isVideo
            ? (int) config('filament-page-blocks.media.video_max_size', 51200)
            : (int) config('filament-page-blocks.media.image_max_size', 5120);
        $maxBytes = max(1, $maxSize) * 1024;
        if (($file->getSize() ?: 0) > $maxBytes) {
            throw new InvalidArgumentException('Media file exceeds the configured maximum size.');
        }

        $filesystem = $this->filesystem($definition);
        $directory = $this->fullPath($definition, $this->allowedPath($definition, $path));
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $allowedExtensions = $isVideo
            ? ['mp4', 'webm', 'mov', 'm4v']
            : ['jpg', 'jpeg', 'png', 'webp'];
        if (! in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException("Media extension [{$extension}] is not allowed.");
        }
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'media';
        $filename = $base.'.'.$extension;
        $counter = 2;
        while ($filesystem->exists(trim($directory.'/'.$filename, '/'))) {
            $filename = $base.'-'.$counter++.'.'.$extension;
        }

        $stored = $filesystem->putFileAs($directory, $file, $filename);
        if (! is_string($stored)) {
            throw new RuntimeException('Unable to store media file.');
        }

        return $this->relativePath($definition, $stored);
    }

    public function createFolder(string $collection, string $path, string $name): void
    {
        $definition = $this->writableDefinition($collection);
        $name = trim($name);
        if (preg_match('/^[\pL\pN][\pL\pN _-]{0,63}$/u', $name) !== 1) {
            throw new InvalidArgumentException('Folder name contains unsupported characters.');
        }

        $folder = $this->fullPath($definition, $this->allowedPath($definition, trim($this->normalize($path).'/'.$name, '/')));
        if (! $this->filesystem($definition)->makeDirectory($folder)) {
            throw new RuntimeException('Unable to create media folder.');
        }
    }

    public function delete(string $collection, string $path): void
    {
        $definition = $this->writableDefinition($collection);
        $path = $this->fullPath($definition, $this->allowedPath($definition, $path));
        $filesystem = $this->filesystem($definition);

        if (! $filesystem->exists($path) || ! $filesystem->delete($path)) {
            throw new RuntimeException('Unable to delete media file.');
        }
    }

    public function normalize(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/ ');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, "\0")) {
                throw new InvalidArgumentException('Invalid media path.');
            }
        }

        return implode('/', $segments);
    }

    /** @return array<string, mixed> */
    private function definition(string $collection): array
    {
        $definition = $this->collections()[$collection] ?? null;
        if (! is_array($definition)) {
            throw new InvalidArgumentException("Unknown media collection [{$collection}].");
        }

        return $definition;
    }

    /** @return array<string, mixed> */
    private function writableDefinition(string $collection): array
    {
        $definition = $this->definition($collection);
        if (! ($definition['writable'] ?? false)) {
            throw new InvalidArgumentException('This media collection is read-only.');
        }

        return $definition;
    }

    /** @param array<string, mixed> $definition */
    private function filesystem(array $definition): FilesystemAdapter
    {
        if (is_string($definition['disk'] ?? null) && $definition['disk'] !== '') {
            return Storage::disk($definition['disk']);
        }

        $root = $definition['root'] ?? null;
        if (! is_string($root) || $root === '') {
            throw new InvalidArgumentException('A media collection requires disk or root.');
        }

        return Storage::build([
            'driver' => 'local',
            'root' => $root,
            'url' => (string) ($definition['url'] ?? url('/')),
            'visibility' => 'public',
            'throw' => false,
        ]);
    }

    /** @param array<string, mixed> $definition */
    private function fullPath(array $definition, string $path): string
    {
        return trim(trim((string) ($definition['directory'] ?? ''), '/').'/'.$path, '/');
    }

    /** @param array<string, mixed> $definition */
    private function relativePath(array $definition, string $path): string
    {
        $directory = trim((string) ($definition['directory'] ?? ''), '/');
        if ($directory !== '' && str_starts_with($path, $directory.'/')) {
            $path = substr($path, strlen($directory) + 1);
        }

        return trim($path, '/');
    }

    /** @param array<string, mixed> $definition */
    private function allowedPath(array $definition, string $path): string
    {
        $path = $this->normalize($path);
        if ($this->isExcluded($definition, $path)) {
            throw new InvalidArgumentException('This media path belongs to another collection.');
        }

        return $path;
    }

    /** @param array<string, mixed> $definition */
    private function isExcluded(array $definition, string $path): bool
    {
        foreach ((array) ($definition['exclude'] ?? []) as $excluded) {
            if (! is_string($excluded)) {
                continue;
            }
            $excluded = trim($excluded, '/');
            if ($excluded !== '' && ($path === $excluded || str_starts_with($path, $excluded.'/'))) {
                return true;
            }
        }

        return false;
    }

    /** @return array{type: 'image'|'video', mime_type: string}|null */
    private function mediaType(FilesystemAdapter $filesystem, string $path): ?array
    {
        try {
            $mimeType = (string) $filesystem->mimeType($path);
            if (str_starts_with($mimeType, 'image/')) {
                return ['type' => 'image', 'mime_type' => $mimeType];
            }
            if (str_starts_with($mimeType, 'video/')) {
                return ['type' => 'video', 'mime_type' => $mimeType];
            }
        } catch (RuntimeException) {
            // Fall back to the extension for filesystems without MIME detection.
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $imageTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
        ];
        if (isset($imageTypes[$extension])) {
            return ['type' => 'image', 'mime_type' => $imageTypes[$extension]];
        }

        $videoTypes = [
            'mp4' => 'video/mp4',
            'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'ogv' => 'video/ogg',
        ];

        return isset($videoTypes[$extension])
            ? ['type' => 'video', 'mime_type' => $videoTypes[$extension]]
            : null;
    }
}
