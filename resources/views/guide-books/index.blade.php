@extends('guide-books.layout')
@section('content')
    <div class="intro"><h1>{{ __('guide_books.navigation') }}</h1><p class="muted">{{ __('guide_books.introduction') }}</p></div>
    <div class="grid">
    @forelse ($guides as $guide)
        <article class="card">
            <span class="tag">{{ strtolower(pathinfo($guide->file_path, PATHINFO_EXTENSION)) === 'pdf' ? 'PDF' : __('guide_books.image') }}</span>
            <h2>{{ $guide->localized('title') }}</h2>
            @if ($guide->localized('description'))<p class="muted">{{ $guide->localized('description') }}</p>@endif
            <a class="button" href="{{ route('guide-books.show', $guide) }}">{{ __('guide_books.open') }}</a>
        </article>
    @empty
        <div class="card empty"><h2>{{ __('guide_books.empty') }}</h2><p class="muted">{{ __('guide_books.empty_help') }}</p></div>
    @endforelse
    </div>
    @if ($guides->hasPages())
        <nav class="pagination links" aria-label="{{ __('guide_books.navigation') }}">
            @if ($guides->previousPageUrl())<a href="{{ $guides->previousPageUrl() }}">{{ __('guide_books.previous') }}</a>@endif
            <span>{{ $guides->currentPage() }} / {{ $guides->lastPage() }}</span>
            @if ($guides->nextPageUrl())<a href="{{ $guides->nextPageUrl() }}">{{ __('guide_books.next') }}</a>@endif
        </nav>
    @endif
@endsection
