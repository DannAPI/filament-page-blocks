<?php

declare(strict_types=1);

namespace DannAPI\FilamentPageBlocks\Filament\Pages;

use DannAPI\FilamentPageBlocks\Support\MediaLibrary;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

final class MediaLibraryPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $title = 'Media';

    protected static ?string $slug = 'media';

    protected string $view = 'filament-page-blocks::filament.media-library';

    public string $collection = '';

    public string $path = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    public string $folderName = '';

    public ?string $previewUrl = null;

    public ?string $previewName = null;

    public ?string $previewType = null;

    public ?string $previewMimeType = null;

    public static function canAccess(): bool
    {
        return Gate::allows('media.viewAny');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return config('filament-page-blocks.filament.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-page-blocks.filament.navigation_sort', 10) + 4;
    }

    public function mount(MediaLibrary $library): void
    {
        $collections = $library->collections();
        $default = (string) config('filament-page-blocks.media.library.default_collection', 'system');
        $this->collection = isset($collections[$default]) ? $default : (string) array_key_first($collections);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $library = app(MediaLibrary::class);

        return [
            'collections' => $library->collections(),
            'entries' => $library->entries($this->collection, $this->path),
            'writable' => $library->isWritable($this->collection),
            'breadcrumbs' => $this->breadcrumbs(),
        ];
    }

    public function selectCollection(string $collection): void
    {
        $library = app(MediaLibrary::class);
        abort_unless(isset($library->collections()[$collection]), 404);
        $this->collection = $collection;
        $this->path = '';
        $this->reset('uploads', 'folderName');
    }

    public function openFolder(string $path): void
    {
        $this->path = app(MediaLibrary::class)->path($this->collection, $path);
    }

    public function goTo(string $path): void
    {
        $this->path = app(MediaLibrary::class)->path($this->collection, $path);
    }

    public function upload(): void
    {
        Gate::authorize('media.upload');
        abort_unless(app(MediaLibrary::class)->isWritable($this->collection), 403);

        $imageMimeTypes = (array) config('filament-page-blocks.media.image_mime_types', []);
        $videoMimeTypes = (array) config('filament-page-blocks.media.video_mime_types', []);
        $mimeTypes = implode(',', array_values(array_unique([...$imageMimeTypes, ...$videoMimeTypes])));
        $maxSize = max(
            1,
            (int) config('filament-page-blocks.media.image_max_size', 5120),
            (int) config('filament-page-blocks.media.video_max_size', 51200),
        );
        $this->validate([
            'uploads' => ['required', 'array', 'max:20'],
            'uploads.*' => ['required', 'file', 'mimetypes:'.$mimeTypes, 'max:'.$maxSize],
        ]);

        try {
            foreach ($this->uploads as $upload) {
                app(MediaLibrary::class)->upload($this->collection, $this->path, $upload);
            }
            $this->reset('uploads');
            Notification::make()->title('Media uploaded')->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Upload failed')->body($exception->getMessage())->danger()->send();
        }
    }

    public function createFolder(): void
    {
        Gate::authorize('media.update');

        try {
            app(MediaLibrary::class)->createFolder($this->collection, $this->path, $this->folderName);
            $this->reset('folderName');
            Notification::make()->title('Folder created')->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Unable to create folder')->body($exception->getMessage())->danger()->send();
        }
    }

    public function deleteFile(string $path): void
    {
        Gate::authorize('media.delete');

        try {
            app(MediaLibrary::class)->delete($this->collection, $path);
            Notification::make()->title('Media deleted')->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Unable to delete media')->body($exception->getMessage())->danger()->send();
        }
    }

    public function previewFile(string $path): void
    {
        Gate::authorize('media.viewAny');

        try {
            $entry = app(MediaLibrary::class)->entry($this->collection, $path);
            $this->previewUrl = $entry['url'];
            $this->previewName = $entry['name'];
            $this->previewType = $entry['type'];
            $this->previewMimeType = $entry['mime_type'];
            $this->dispatch('open-modal', id: 'fpb-media-preview');
        } catch (Throwable $exception) {
            Notification::make()->title('Unable to preview media')->body($exception->getMessage())->danger()->send();
        }
    }

    /** @return array<string, string> */
    private function breadcrumbs(): array
    {
        $breadcrumbs = ['' => 'Root'];
        $path = '';
        foreach (array_filter(explode('/', $this->path)) as $segment) {
            $path = trim($path.'/'.$segment, '/');
            $breadcrumbs[$path] = $segment;
        }

        return $breadcrumbs;
    }
}
