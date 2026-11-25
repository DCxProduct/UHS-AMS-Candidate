<?php

namespace App\Support;

use BladeUI\Icons\Factory;
use BladeUI\Icons\IconsManifest;
use Illuminate\Support\Str;

class SidebarMenuIcons
{
    /** Keep all installed variants valid, including previously saved selections. */
    public static function all(): array
    {
        $sets = app(Factory::class)->all();
        $options = [];
        foreach (app(IconsManifest::class)->getManifest($sets) as $set => $paths) {
            $prefix = $sets[$set]['prefix'];
            foreach ($paths as $names) {
                foreach ($names as $name) {
                    $label = Str::headline($name);
                    if (in_array($prefix, ['heroicon', 'fi'], true) && preg_match('/^([osmc])-(.+)$/', $name, $parts)) {
                        $label = Str::headline($parts[2]);
                    }
                    $options[$prefix.'-'.$name] = $label;
                }
            }
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /** Show each design once, preferring outline while preserving a saved variant. */
    public static function options(?string $selected = null): array
    {
        $all = self::all();
        $designs = [];
        foreach ($all as $icon => $label) {
            $rank = preg_match('/^(?:heroicon|fi)-([osmc])-/', $icon, $parts)
                ? ['o' => 0, 's' => 1, 'm' => 2, 'c' => 3][$parts[1]] : 0;
            if (! isset($designs[$label]) || $rank < $designs[$label]['rank']) {
                $designs[$label] = ['icon' => $icon, 'rank' => $rank];
            }
        }
        if (isset($all[$selected ?? ''])) {
            $designs[$all[$selected]] = ['icon' => $selected, 'rank' => -1];
        }
        $options = [];
        foreach ($designs as $label => $design) {
            $options[$design['icon']] = $label;
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    public static function htmlOptions(?string $selected = null): array
    {
        $options = [];
        foreach (self::options($selected) as $icon => $label) {
            $options[$icon] = view('filament.admin.sidebar-menus.icon-option', compact('icon', 'label'))->render();
        }

        return $options;
    }

    public static function optionHtml(?string $icon): ?string
    {
        $label = self::all()[$icon ?? ''] ?? null;

        return $label === null ? null : view('filament.admin.sidebar-menus.icon-option', compact('icon', 'label'))->render();
    }
}
