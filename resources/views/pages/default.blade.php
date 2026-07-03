@extends('filament-page-blocks::template')

@section('content')
    {{-- Block views receive $data, $page and $block from the renderer. --}}
    <main>{!! $content !!}</main>
@endsection
