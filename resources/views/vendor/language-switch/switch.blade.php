@php
    $languageSwitch = $languageSwitch ?? \BezhanSalleh\LanguageSwitch\LanguageSwitch::make();

    $isCircular = $isCircular ?? $languageSwitch->isCircular();
    $isFlagsOnly = $isFlagsOnly ?? $languageSwitch->isFlagsOnly();
    $hasFlags = $hasFlags ?? filled($languageSwitch->getFlags());

    $currentLocale = session('locale', app()->getLocale());

    if (! in_array($currentLocale, ['en', 'km'], true)) {
        $currentLocale = 'km';
    }

    $nextLocale = $currentLocale === 'km' ? 'en' : 'km';

    $toggleUrl = \Illuminate\Support\Facades\Route::has('language.toggle')
        ? route('language.toggle')
        : url('/language/toggle');

    $currentFlag = $languageSwitch->getFlag($currentLocale);

    if (blank($currentFlag)) {
        $currentFlag = $currentLocale === 'km'
            ? 'https://flagcdn.com/w80/kh.png'
            : 'https://flagcdn.com/w80/gb.png';
    }

    $title = $currentLocale === 'km'
        ? 'Switch to English'
        : 'ប្តូរទៅភាសាខ្មែរ';
@endphp

<a
    href="{{ $toggleUrl }}"
    title="{{ $title }}"
    aria-label="{{ $title }}"
    class="uhs-one-click-language"
>
    @if ($isFlagsOnly || $hasFlags)
        <x-language-switch::flag
            :src="$currentFlag"
            :circular="$isCircular"
            :alt="$languageSwitch->getLabel($currentLocale)"
            :switch="true"
            class="uhs-one-click-language-flag"
        />
    @else
        <span class="uhs-one-click-language-text">
            {{ $languageSwitch->getCharAvatar($currentLocale) }}
        </span>
    @endif
</a>
