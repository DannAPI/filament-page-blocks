<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['main' => $page->is_homepage])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta name="format-detection" content="telephone=no">
    <title>{{ $page->seo_title ?: $page->title }}</title>
    @if ($page->seo_description)
        <meta name="description" content="{{ $page->seo_description }}">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $page->seo_title ?: $page->title }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($page->seo_description)
        <meta property="og:description" content="{{ $page->seo_description }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $page->seo_title ?: $page->title }}">
    @if ($page->seo_description)
        <meta name="twitter:description" content="{{ $page->seo_description }}">
    @endif
    @if ($assets->favicon)
        <link rel="icon" href="{{ $assets->url($assets->favicon) }}">
    @endif
    @foreach ($assets->styles as $stylesheet)
        <link rel="stylesheet" href="{{ $assets->url($stylesheet) }}">
    @endforeach
    @stack('head')
    @stack('styles')
</head>
<body @class(['page', 'page--home' => $page->is_homepage, 'page--'.$page->template])>
    @include((string) config('filament-page-blocks.frontend.header_view'))

    @yield('content')

    @include((string) config('filament-page-blocks.frontend.footer_view'))

    @foreach ($assets->scripts as $script)
        <script src="{{ $assets->url($script) }}"></script>
    @endforeach
    @stack('scripts')
</body>
</html>
