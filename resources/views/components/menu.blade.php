<ul>
    @foreach ($items as $item)
        @continue(! $item->is_visible)
        @php
            $children = $item->children->where('is_visible', true)->values();
            $isActive = $item->isActive($page);
            $hasChildren = $children->isNotEmpty();
        @endphp
        <li @if ($isActive) class="active" @endif>
            <a
                href="{{ $item->href() }}"
                @if ($hasChildren) class="with-drop" @endif
                @if ($item->target === '_blank') target="_blank" rel="noopener noreferrer" @endif
            >{{ $item->label }}</a>
            @if ($hasChildren)
                <div class="drop">
                    @include('filament-page-blocks::components.menu', ['items' => $children])
                </div>
            @endif
        </li>
    @endforeach
</ul>

