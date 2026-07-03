<section class="page-block page-block--image-text page-block--image-{{ $data->get('image_position') }}">
    @if ($url = \DannAPI\FilamentPageBlocks\Support\Media::url($data->get('image')))
        <img src="{{ $url }}" alt="{{ $data->get('heading') }}">
    @endif
    <div><h2>{{ $data->get('heading') }}</h2><p>{{ $data->get('text') }}</p></div>
</section>
