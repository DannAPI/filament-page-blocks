<section class="page-block page-block--hero">
    @if ($url = \DannAPI\FilamentPageBlocks\Support\Media::url($data->get('image')))
        <img src="{{ $url }}" alt="{{ $data->get('title') }}">
    @endif
    <h1>{{ $data->get('title') }}</h1>
    @if ($data->get('text'))<p>{{ $data->get('text') }}</p>@endif
    @if ($data->get('button_text') && $data->get('button_url'))
        <a href="{{ $data->get('button_url') }}">{{ $data->get('button_text') }}</a>
    @endif
</section>
