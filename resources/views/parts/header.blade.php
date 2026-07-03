@php
    /** @var \DannAPI\FilamentPageBlocks\Data\FrontendContext $frontend */
@endphp

<header class="header {{ $frontend->is('home') ? 'header_home' : 'header_dark' }}">
    <a href="{{ $frontend->url() }}" class="header__logo header__logo_main">
        <img src="{{ $frontend->asset('img/logo.png') }}" alt="{{ $site['name'] ?? config('app.name') }}" width="197">
    </a>
    <div class="header__inner">
        <nav class="header__nav">
            <a href="{{ $frontend->url() }}" class="header__logo header__logo_nav">
                <img src="{{ $frontend->asset('img/logo.png') }}" alt="{{ $site['name'] ?? config('app.name') }}" width="197">
            </a>
            @if ($headerMenu)
                @include('filament-page-blocks::components.menu', ['items' => $headerMenu->items])
            @endif
            <div class="header__mob-buttons d-md-none">
                <a href="{{ $frontend->url('pro-register') }}" class="btn btn_white-outline w-100">Pro register</a>
            </div>
        </nav>
        <div class="header__buttons">
            <div class="header__lang">
                <img src="{{ $frontend->asset('img/us.png') }}" alt="English">
                <span>English</span>
                <i class="fa-solid fa-angle-down"></i>
                <div class="drop">
                    <ul>
                        <li>
                            <a href="#" class="current">
                                <img src="{{ $frontend->asset('img/us.png') }}" alt="English">
                                <span>English</span>
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <img src="{{ $frontend->asset('img/es.png') }}" alt="Español">
                                <span>Español</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <a href="{{ $frontend->url('pro-register') }}" class="btn btn_white-outline d-none d-md-block">Pro register</a>
        </div>
        <button class="header__open-nav" type="button" aria-label="Toggle navigation">
            <span>toggle menu</span>
        </button>
    </div>
</header>
