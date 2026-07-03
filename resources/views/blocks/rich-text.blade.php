<section class="page-block page-block--rich-text">
    @if ($data->get('heading'))<h2>{{ $data->get('heading') }}</h2>@endif
    <div>{!! app(\DannAPI\FilamentPageBlocks\Rendering\RichTextSanitizer::class)->sanitize((string) $data->get('content', '')) !!}</div>
</section>
