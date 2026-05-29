@php
    $languageSwitch = \BezhanSalleh\LanguageSwitch\LanguageSwitch::make();
    $locales = $languageSwitch->getLocales();
    $isCircular = $languageSwitch->isCircular();
    $isFlagsOnly = $languageSwitch->isFlagsOnly();
    $hasFlags = filled($languageSwitch->getFlags());
    $isVisibleOutsidePanels = $languageSwitch->isVisibleOutsidePanels();
    $outsidePanelsPlacement = $languageSwitch->getOutsidePanelPlacement()->value;

    $defaultPlacement = __('filament-panels::layout.direction') === 'rtl' ? 'bottom-start' : 'bottom-end';

    $placement = match (true) {
        $outsidePanelsPlacement === 'top-center' && $isFlagsOnly => 'bottom',
        $outsidePanelsPlacement === 'bottom-center' && $isFlagsOnly => 'top',
        !$isVisibleOutsidePanels && $isFlagsOnly => 'bottom',
        default => $defaultPlacement,
    };

    $maxHeight = $languageSwitch->getMaxHeight();

    $currentLocale = app()->getLocale();
    $nextLocale = $currentLocale === 'km' ? 'en' : 'km';

    $flags = $languageSwitch->getFlags();

    $flag = $flags[$currentLocale] ?? (
        $currentLocale === 'km'
            ? 'https://flagcdn.com/w80/kh.png'
            : 'https://flagcdn.com/w80/gb.png'
    );

    $title = $currentLocale === 'km'
        ? 'Switch to English'
        : 'ប្តូរទៅភាសាខ្មែរ';

    $toggleUrl = \Illuminate\Support\Facades\Route::has('language.toggle')
        ? route('language.toggle')
        : url('/language/toggle');
@endphp

<div>
    @if ($isVisibleOutsidePanels)
        <div @class([
            'fls-display-on fixed w-fit flex p-4 z-50',
            'top-0' => str_contains($outsidePanelsPlacement, 'top'),
            'bottom-0' => str_contains($outsidePanelsPlacement, 'bottom'),
            'justify-start' => str_contains($outsidePanelsPlacement, 'left'),
            'justify-end' => str_contains($outsidePanelsPlacement, 'right'),
            'justify-center' => str_contains($outsidePanelsPlacement, 'center'),
        ])>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-950">
                <a
                    href="{{ $toggleUrl }}"
                    title="{{ $title }}"
                    aria-label="{{ $title }}"
                    class="uhs-one-click-language"
                >
                    <img
                        src="{{ $flag }}"
                        alt="{{ $currentLocale }}"
                        class="uhs-one-click-language-flag"
                    >
                </a>
            </div>
        </div>
    @else
        <a
            href="{{ $toggleUrl }}"
            title="{{ $title }}"
            aria-label="{{ $title }}"
            class="uhs-one-click-language"
        >
            <img
                src="{{ $flag }}"
                alt="{{ $currentLocale }}"
                class="uhs-one-click-language-flag"
            >
        </a>
    @endif
</div>
