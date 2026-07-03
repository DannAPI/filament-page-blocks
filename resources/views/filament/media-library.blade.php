<x-filament-panels::page>
    <div class="fpb-media-library">
        <div class="fpb-media-library__collections">
            @foreach ($collections as $key => $definition)
                <button
                    type="button"
                    wire:click="selectCollection({{ \Illuminate\Support\Js::from($key) }})"
                    @class(['fpb-media-library__collection', 'is-active' => $collection === $key])
                >
                    <x-filament::icon icon="heroicon-o-folder" />
                    {{ $definition['label'] ?? $key }}
                    @if (! ($definition['writable'] ?? false))
                        <span>Read only</span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="fpb-media-library__toolbar">
            <nav aria-label="Media breadcrumbs">
                @foreach ($breadcrumbs as $breadcrumbPath => $label)
                    <button type="button" wire:click="goTo({{ \Illuminate\Support\Js::from($breadcrumbPath) }})">{{ $label }}</button>
                    @unless ($loop->last)<span>/</span>@endunless
                @endforeach
            </nav>

            @if ($writable)
                <div class="fpb-media-library__actions">
                    @can('media.update')
                        <form wire:submit="createFolder">
                            <input wire:model="folderName" type="text" maxlength="64" placeholder="New folder">
                            <x-filament::button type="submit" color="gray" size="sm">Create folder</x-filament::button>
                        </form>
                    @endcan
                    @can('media.upload')
                        <form wire:submit="upload">
                            <input
                                wire:model="uploads"
                                type="file"
                                accept="{{ implode(',', array_values(array_unique([
                                    ...(array) config('filament-page-blocks.media.image_mime_types', []),
                                    ...(array) config('filament-page-blocks.media.video_mime_types', []),
                                ]))) }}"
                                multiple
                            >
                            <x-filament::button type="submit" size="sm" wire:loading.attr="disabled">Upload</x-filament::button>
                        </form>
                    @endcan
                </div>
            @endif
        </div>

        @error('uploads.*')<p class="fpb-media-library__error">{{ $message }}</p>@enderror

        <div class="fpb-media-library__grid">
            @forelse ($entries as $entry)
                @if ($entry['type'] === 'folder')
                    <button type="button" class="fpb-media-library__folder" wire:click="openFolder({{ \Illuminate\Support\Js::from($entry['path']) }})">
                        <x-filament::icon icon="heroicon-o-folder" />
                        <strong>{{ $entry['name'] }}</strong>
                    </button>
                @else
                    <article class="fpb-media-library__media" wire:key="media-{{ md5($collection.$entry['path']) }}">
                        <button
                            type="button"
                            class="fpb-media-library__preview"
                            wire:click="previewFile({{ \Illuminate\Support\Js::from($entry['path']) }})"
                            x-on:click="window.dispatchEvent(new CustomEvent('fpb-media-stop-videos'))"
                            aria-label="Preview {{ $entry['name'] }}"
                        >
                            @if ($entry['type'] === 'video')
                                <span class="fpb-media-library__video-placeholder">
                                    <x-filament::icon icon="heroicon-o-play-circle" />
                                    <span>{{ strtoupper(pathinfo($entry['name'], PATHINFO_EXTENSION)) }}</span>
                                </span>
                            @else
                                <img src="{{ $entry['url'] }}" alt="{{ $entry['name'] }}" loading="lazy">
                            @endif
                        </button>
                        <div>
                            <strong title="{{ $entry['name'] }}">{{ $entry['name'] }}</strong>
                            <small>{{ ucfirst($entry['type']) }} · {{ number_format(((int) $entry['size']) / 1024, 1) }} KB</small>
                        </div>
                        @if ($entry['writable'])
                            @can('media.delete')
                                <button
                                    type="button"
                                    wire:click="deleteFile({{ \Illuminate\Support\Js::from($entry['path']) }})"
                                    wire:confirm="Delete this media file? Existing pages or models may still reference it."
                                >Delete</button>
                            @endcan
                        @endif
                    </article>
                @endif
            @empty
                <div class="fpb-media-library__empty">No images or videos in this folder.</div>
            @endforelse
        </div>

        <x-filament::modal
            id="fpb-media-preview"
            width="5xl"
            close-button
            sticky-header
            teleport="body"
            :heading="$previewName ?: 'Media preview'"
        >
            <div
                class="fpb-media-library__modal"
                x-data="{
                    stopVideos() {
                        this.$root.querySelectorAll('video').forEach((video) => {
                            video.pause()
                            try { video.currentTime = 0 } catch (error) {}
                        })
                    },
                }"
                x-on:fpb-media-stop-videos.window="stopVideos()"
                x-on:close-modal.window="if ($event.detail.id === 'fpb-media-preview') stopVideos()"
                x-on:close-modal-quietly.window="if ($event.detail.id === 'fpb-media-preview') stopVideos()"
                x-on:modal-closed.window="if ($event.detail.id === 'fpb-media-preview') stopVideos()"
            >
                @if ($previewType === 'video' && $previewUrl)
                    <video
                        wire:key="media-preview-video-{{ md5($previewUrl) }}"
                        controls
                        playsinline
                        preload="metadata"
                    >
                        <source src="{{ $previewUrl }}" type="{{ $previewMimeType }}">
                        Your browser does not support this video format.
                    </video>
                @elseif ($previewType === 'image' && $previewUrl)
                    <img src="{{ $previewUrl }}" alt="{{ $previewName }}">
                @endif
            </div>
        </x-filament::modal>
    </div>

    <style>
        .fpb-media-library__collections, .fpb-media-library__toolbar, .fpb-media-library__actions, .fpb-media-library__actions form { display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; }
        .fpb-media-library__collections { margin-bottom:1rem; }
        .fpb-media-library__collection { padding:.65rem 1rem; border:1px solid var(--gray-300); border-radius:.6rem; background:var(--gray-50); text-align:left; display:grid; grid-template-columns:1.25rem auto; column-gap:.5rem; align-items:center; }
        .fpb-media-library__collection svg { width:1.25rem; height:1.25rem; color:var(--primary-500); grid-row:1/3; }
        .fpb-media-library__collection.is-active { color:var(--primary-600); border-color:var(--primary-500); background:var(--primary-50); }
        .fpb-media-library__collection span { display:block; font-size:.7rem; opacity:.7; }
        .fpb-media-library__toolbar { justify-content:space-between; margin-bottom:1rem; }
        .fpb-media-library__toolbar nav { display:flex; gap:.35rem; align-items:center; }
        .fpb-media-library__toolbar nav button { color:var(--primary-600); }
        .fpb-media-library__actions input { border:1px solid var(--gray-300); border-radius:.5rem; padding:.45rem .65rem; background:white; }
        .fpb-media-library__grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(175px,1fr)); gap:1rem; }
        .fpb-media-library__folder, .fpb-media-library__media { min-width:0; border:1px solid var(--gray-200); border-radius:.75rem; background:white; overflow:hidden; box-shadow:0 1px 2px rgb(0 0 0 / .04); }
        .fpb-media-library__folder { min-height:150px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.75rem; padding:1rem; }
        .fpb-media-library__folder svg { width:3rem; height:3rem; color:var(--primary-500); }
        .fpb-media-library__preview { display:block; width:100%; height:145px; background:var(--gray-100); cursor:zoom-in; }
        .fpb-media-library__preview img { width:100%; height:100%; object-fit:cover; }
        .fpb-media-library__video-placeholder { display:flex; width:100%; height:100%; flex-direction:column; align-items:center; justify-content:center; gap:.5rem; color:var(--primary-600); }
        .fpb-media-library__video-placeholder svg { width:3.5rem; height:3.5rem; }
        .fpb-media-library__video-placeholder span { font-size:.75rem; font-weight:600; }
        .fpb-media-library__media > div { padding:.65rem; display:flex; flex-direction:column; }
        .fpb-media-library__media strong { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .fpb-media-library__media small { color:var(--gray-500); }
        .fpb-media-library__media > button:last-child { width:100%; height:auto; padding:.45rem; color:var(--danger-600); border-top:1px solid var(--gray-200); cursor:pointer; }
        .fpb-media-library__modal { display:flex; min-height:12rem; align-items:center; justify-content:center; background:#000; border-radius:.75rem; overflow:hidden; }
        .fpb-media-library__modal img, .fpb-media-library__modal video { display:block; width:100%; max-width:100%; max-height:75vh; object-fit:contain; }
        .fpb-media-library__empty { grid-column:1/-1; padding:3rem; text-align:center; color:var(--gray-500); border:1px dashed var(--gray-300); border-radius:.75rem; }
        .fpb-media-library__error { color:var(--danger-600); margin-bottom:1rem; }
        .dark .fpb-media-library__collection, .dark .fpb-media-library__folder, .dark .fpb-media-library__media, .dark .fpb-media-library__actions input { background:var(--gray-900); border-color:var(--gray-700); }
    </style>
</x-filament-panels::page>
