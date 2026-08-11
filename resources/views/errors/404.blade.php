@extends('filament-page-blocks::template')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <main>
        <section class="error error_page">
            <article class="error__inner">
                <div class="error__oops wow bounceInDown" data-wow-duration="1.5s">
                    <div class="error__image" style="background-image: url('{{ $frontend->asset('img/demo/error.jpg') }}')"></div>
                </div>
                <div class="error__text wow bounceInUp" data-wow-duration="1.5s">
                    <h1>404 - PAGE NOT FOUND</h1>
                    <p>The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
                    <a href="{{ $homeUrl }}" class="btn">Go to homepage</a>
                </div>
            </article>
        </section>
    </main>
@endsection
