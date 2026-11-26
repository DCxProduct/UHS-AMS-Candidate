@php
    $loginGuide = \Illuminate\Support\Facades\Schema::hasTable('system_guide_books')
        ? \App\Models\GuideBook::query()->published()->orderBy('id')->first()
        : null;
    $hasGuide = $loginGuide && \App\Models\GuideBook::validFilePath($loginGuide->file_path);
@endphp

<div style="text-align: center; margin-top: 1.25rem;">
    <x-filament::modal
        id="login-guide-book"
        width="7xl"
        teleport="body"
        sticky-header
        sticky-footer
    >
        <x-slot name="trigger" style="justify-content: center;">
            <button type="button" aria-haspopup="dialog" aria-controls="login-guide-book"
                style="display: inline-flex; align-items: center; gap: .5rem; color: #2446b7; font-weight: 600; text-decoration: underline; text-underline-offset: 4px; cursor: pointer; background: transparent; border: 0; padding: 0; font: inherit;">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a6 6 0 0 0-9-1.5v14.25a6 6 0 0 1 9 1.5m0-14.25a6 6 0 0 1 9-1.5v14.25a6 6 0 0 0-9 1.5m0-14.25V21"/></svg>
                {{ __('guide_books.login_link') }}
            </button>
        </x-slot>

        <x-slot name="heading">
            {{ $hasGuide ? $loginGuide->localized('title') : __('guide_books.navigation') }}
        </x-slot>

        @if ($hasGuide)
            <template x-if="isOpen">
                @if (strtolower(pathinfo($loginGuide->file_path, PATHINFO_EXTENSION)) === 'pdf')
                    <iframe src="{{ route('guide-books.file', $loginGuide) }}"
                        title="{{ $loginGuide->localized('title') }}"
                        style="display: block; width: 100%; height: 65vh; min-height: 280px; border: 1px solid #e2e6ee; border-radius: .5rem; background: #fff;"></iframe>
                @else
                    <div style="max-height: 65vh; overflow: auto;">
                        <img src="{{ route('guide-books.file', $loginGuide) }}"
                            alt="{{ $loginGuide->localized('title') }}"
                            style="display: block; max-width: 100%; height: auto; margin: auto; border-radius: .5rem;">
                    </div>
                @endif
            </template>
        @else
            <p>{{ __('guide_books.empty') }}</p>
            <p style="color: #647084;">{{ __('guide_books.empty_help') }}</p>
        @endif

        <x-slot name="footer">
            <div style="display: flex; align-items: center; justify-content: flex-end; gap: .75rem; flex-wrap: wrap;">
                @if ($hasGuide)
                    <x-filament::button tag="a" href="{{ route('guide-books.file', ['guideBook' => $loginGuide, 'download' => 1]) }}"
                        icon="heroicon-o-arrow-down-tray" download>
                        {{ __('guide_books.download') }}
                    </x-filament::button>
                @endif
                <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: 'login-guide-book' })">
                    {{ __('guide_books.close') }}
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>
</div>
