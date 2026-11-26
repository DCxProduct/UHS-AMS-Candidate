@extends('guide-books.layout')
@section('title', $guide->localized('title'))
@section('content')
    <a href="{{ route('guide-books.index') }}">← {{ __('guide_books.all_guides') }}</a>
    <h1 style="margin-top:18px">{{ $guide->localized('title') }}</h1>
    @if ($guide->localized('description'))<p class="muted" style="white-space:pre-line">{{ $guide->localized('description') }}</p>@endif
    <div class="actions">
        <a class="button" href="{{ route('guide-books.file', ['guideBook' => $guide, 'download' => 1]) }}">{{ __('guide_books.download') }}</a>
        <a href="{{ route('guide-books.file', $guide) }}" target="_blank" rel="noopener">{{ __('guide_books.open_file') }}</a>
    </div>
    @if (strtolower(pathinfo($guide->file_path, PATHINFO_EXTENSION)) === 'pdf')
        <iframe class="preview" src="{{ route('guide-books.file', $guide) }}" title="{{ $guide->localized('title') }}"></iframe>
        <p class="muted">{{ __('guide_books.pdf_help') }}</p>
    @else
        <div class="card"><img class="guide-image" src="{{ route('guide-books.file', $guide) }}" alt="{{ $guide->localized('title') }}"></div>
    @endif
@endsection
