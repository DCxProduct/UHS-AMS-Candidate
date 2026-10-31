<?php

namespace App\Support;

use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Str;

/** Resolve staff message names at delivery time without changing the saved template. */
final class WorkflowMessagePlaceholders
{
    public static function render(string $message, CustomFormEntry $entry): string
    {
        $pattern = '/:(candidate|canddiate|form)(?![A-Za-z0-9_])/';
        if (! preg_match($pattern, $message)) {
            return $message;
        }

        $entry->loadMissing(['customForm', 'creator']);
        $candidate = self::candidateName($entry);
        $form = (string) ($entry->customForm?->display_name ?? '');

        return preg_replace_callback($pattern, static fn (array $match): string => $match[1] === 'form' ? $form : $candidate, $message);
    }

    private static function candidateName(CustomFormEntry $entry): string
    {
        $data = is_array($entry->data) ? $entry->data : [];
        $khmer = self::firstText($data, ['full_name_kh', 'name_kh', 'name_khmer'])
            ?: self::joinedName($data, ['first_name_kh', 'first_name_khmer', 'surname_kh'], ['last_name_kh', 'last_name_khmer', 'given_name_kh']);
        $latin = self::firstText($data, ['full_name_en', 'name_en', 'name_latin'])
            ?: self::joinedName($data, ['first_name_en', 'first_name_latin'], ['last_name_en', 'last_name_latin']);
        $name = app()->getLocale() === 'km' ? ($khmer ?: $latin) : ($latin ?: $khmer);
        $name = $name ?: self::firstText($data, ['full_name', 'candidate_name', 'name']);
        if ($name !== '') {
            return $name;
        }

        $owner = $entry->creator;
        if ($owner instanceof User && $owner->registration_type === 'student') {
            $khmer = self::joinedName($owner->getAttributes(), ['first_name_kh'], ['last_name_kh']);
            $latin = self::firstText($owner->getAttributes(), ['name_latin']);

            return (app()->getLocale() === 'km' ? ($khmer ?: $latin) : ($latin ?: $khmer))
                ?: CandidateDisplayName::for($owner);
        }

        return __('workflow_notifications.view.candidate');
    }

    private static function joinedName(array $data, array $firstKeys, array $lastKeys): string
    {
        return trim(self::firstText($data, $firstKeys).' '.self::firstText($data, $lastKeys));
    }

    private static function firstText(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            $value = is_scalar($value) ? Str::squish(strip_tags((string) $value)) : '';
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
