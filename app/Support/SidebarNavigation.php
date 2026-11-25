<?php

namespace App\Support;

use App\Models\Role;
use App\Models\SidebarMenu;
use App\Models\SidebarMenuName;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Support\Contracts\ScalableIcon;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SidebarNavigation
{
    private ?array $catalog = null;

    public static function groupKey(?string $label): string
    {
        if (blank($label)) {
            return 'group:ungrouped';
        }

        foreach (['dashboard', 'form_entry', 'cashier', 'candidates', 'form_builder', 'settings', 'activity'] as $key) {
            $translation = $key === 'activity' ? 'audit_logs.activity_navigation_label' : 'navigation.groups.'.$key;
            foreach (['en', 'km'] as $locale) {
                if ($label === __($translation, [], $locale)) {
                    return 'group:'.$key;
                }
            }
        }

        return 'group:'.hash('sha256', $label);
    }

    public static function itemKey(NavigationItem $item): string
    {
        // Filament resources/pages and dynamic forms have locale-independent keys.
        $key = $item->getKey();
        if ($key !== $item->getLabel()) {
            return 'item:'.$key;
        }

        $parts = parse_url($item->getUrl() ?? '');
        parse_str($parts['query'] ?? '', $query);
        ksort($query);

        return 'item:'.hash('sha256', ($parts['path'] ?? '').'?'.http_build_query($query));
    }

    /** Metadata only: this catalog never registers or grants access to a menu. */
    public function catalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $catalog = [];
        $panel = Filament::getCurrentOrDefaultPanel();
        $originalLocale = app()->getLocale();
        try {
            foreach (['en', 'km'] as $locale) {
                app()->setLocale($locale);
                foreach ($panel->getNavigationGroups() as $index => $group) {
                    $label = $group instanceof NavigationGroup ? $group->getLabel() : $group;
                    $this->addGroup($catalog, $label, $locale, $index + 1);
                }
                foreach ([...$panel->getPages(), ...$panel->getResources()] as $class) {
                    if ($class === CustomFormEntryResource::class) {
                        continue;
                    }
                    // Hidden utility pages (such as the post-submission page) are not sidebar targets.
                    if (is_subclass_of($class, Page::class) && ! $class::shouldRegisterNavigation()) {
                        continue;
                    }
                    foreach ($class::getNavigationItems() as $item) {
                        $this->addItem($catalog, $item, $locale);
                    }
                }
            }
        } finally {
            app()->setLocale($originalLocale);
        }

        if (Schema::hasTable('sidebar_menus')) {
            foreach (SidebarMenu::query()->shared()->where('target_key', 'like', SidebarMenu::CUSTOM_GROUP_TARGET_PREFIX.'%')->get() as $group) {
                $catalog[$group->target_key] = [
                    'kind' => 'group', 'group' => null, 'sort_order' => $group->sort_order,
                    'label_en' => $group->label_en, 'label_km' => $group->label_km,
                ];
            }
        }

        return $this->catalog = $catalog;
    }

    private function addGroup(array &$catalog, ?string $label, string $locale, int $sort = 100): void
    {
        if (blank($label)) {
            return;
        }
        $key = self::groupKey($label);
        $catalog[$key] ??= ['kind' => 'group', 'group' => null, 'sort_order' => $sort];
        $catalog[$key]['label_'.$locale] = $label;
    }

    private function addItem(array &$catalog, NavigationItem $item, string $locale): void
    {
        $group = $item->getGroup();
        if ($group instanceof \UnitEnum) {
            $group = $group->name;
        }
        $this->addGroup($catalog, $group, $locale);
        $key = self::itemKey($item);
        $catalog[$key] ??= ['kind' => 'item', 'group' => self::groupKey($group), 'sort_order' => $item->getSort()];
        $catalog[$key]['label_'.$locale] = $item->getLabel();
        $icon = $item->getIcon();
        $catalog[$key]['icon'] = $icon instanceof ScalableIcon ? $icon->getIconForSize(IconSize::Large)
            : ($icon instanceof \BackedEnum ? $icon->value : $icon);
        foreach ($item->getChildItems() as $child) {
            $this->addItem($catalog, $child, $locale);
        }
    }

    public function groupOptions(): array
    {
        $names = $this->names();
        $settings = $this->sharedSettings();
        $options = [];
        foreach ($this->orderedGroupKeys($settings) as $key) {
            $options[$key] = $this->targetLabel($key, $names);
        }

        return $options;
    }

    public function groupForTarget(?string $key): ?string
    {
        $target = $this->catalog()[$key ?? ''] ?? null;

        return $target ? ($target['kind'] === 'group' ? $key : $target['group']) : null;
    }

    public function menuOptions(?string $group): array
    {
        if (($this->catalog()[$group ?? '']['kind'] ?? null) !== 'group') {
            return [];
        }
        $names = $this->names();
        $options = [$group => __('sidebar_menus.group_heading')];
        foreach ($this->catalog() as $key => $target) {
            if ($target['kind'] === 'item' && $target['group'] === $group) {
                $options[$key] = $this->targetLabel($key, $names);
            }
        }

        return $options;
    }

    public function groupForRecord(SidebarMenu $record): ?string
    {
        $original = $this->groupForTarget($record->target_key);
        if (! str_starts_with($record->target_key, 'group:')
            && ($this->catalog()[$record->navigation_group ?? '']['kind'] ?? null) === 'group') {
            return $record->navigation_group;
        }

        return $original;
    }

    public function names(): Collection
    {
        return Schema::hasTable('sidebar_menu_names')
            ? SidebarMenuName::query()->where('target_key', 'not like', SidebarMenu::CUSTOM_FORM_TARGET_PREFIX.'%')->get()->keyBy('target_key')
            : collect();
    }

    public function targetLabel(string $key, ?Collection $names = null): string
    {
        $target = $this->catalog()[$key] ?? null;
        $locale = app()->getLocale() === 'km' ? 'km' : 'en';

        $fallback = $target['label_'.$locale] ?? $target['label_en'] ?? __('sidebar_menus.unavailable');

        return ($names ?? $this->names())->get($key)?->label($fallback) ?? $fallback;
    }

    public function defaultIconForTarget(?string $key): ?string
    {
        $icon = $this->catalog()[$key ?? '']['icon'] ?? null;

        return is_string($icon) && isset(SidebarMenuIcons::all()[$icon]) ? $icon : null;
    }

    /** Decorate authorized links and include user-created group headings. */
    public function apply(array $groups, ?User $user): array
    {
        if (! $user || ! Schema::hasTable('sidebar_menus')) {
            return $groups;
        }
        $settings = $this->sharedSettings();
        $names = $this->names();
        if ($settings->isEmpty() && $names->isEmpty()) {
            return $groups;
        }
        $groups = $this->regroup($groups, $settings);
        $icons = SidebarMenuIcons::all();

        $decorateItems = function (array|Arrayable $items) use (&$decorateItems, $settings, $names, $icons): array {
            $decorated = [];
            foreach (collect($items)->values() as $position => $item) {
                $item = clone $item;
                $key = self::itemKey($item);
                $setting = $settings->get($key);
                $name = $names->get($key) ?? $setting;
                if ($name) {
                    $item->label($name->label($item->getLabel()));
                }
                if (isset($icons[$setting?->navigation_icon ?? ''])) {
                    $item->icon($setting->navigation_icon)->activeIcon($setting->navigation_icon);
                }
                $item->childItems($decorateItems($item->getChildItems()));
                $decorated[] = ['item' => $item, 'sort' => $setting?->sort_order ?? $item->getSort(), 'position' => $position];
            }

            return collect($decorated)->sortBy([['sort', 'asc'], ['position', 'asc']])->pluck('item')->all();
        };
        $decorated = [];
        foreach ($groups as $key => $group) {
            $position = count($decorated);
            $group = clone $group;
            $setting = $settings->get($key);
            $name = $names->get($key) ?? $setting;
            if ($name) {
                $group->label($name->label($group->getLabel()));
            }
            $group->items($decorateItems($group->getItems()));
            $decorated[] = ['group' => $group, 'sort' => $key === 'group:ungrouped' ? -1 : ($setting?->sort_order ?? $position + 1), 'position' => $position];
        }

        return collect($decorated)->sortBy([['sort', 'asc'], ['position', 'asc']])->pluck('group')->all();
    }

    private function regroup(array $groups, Collection $settings): array
    {
        $templates = [];
        $items = [];
        foreach ($groups as $group) {
            $key = self::groupKey($group->getLabel());
            $templates[$key] = clone $group;
            $items[$key] = [];
        }
        $registered = [];
        foreach (Filament::getCurrentOrDefaultPanel()->getNavigationGroups() as $group) {
            $group = $group instanceof NavigationGroup ? clone $group : NavigationGroup::make($group);
            $registered[self::groupKey($group->getLabel())] = $group;
        }
        $addedGroup = false;
        foreach ($this->catalog() as $key => $target) {
            if (str_starts_with($key, SidebarMenu::CUSTOM_GROUP_TARGET_PREFIX)) {
                $registered[$key] = NavigationGroup::make($this->targetLabel($key));
                $templates[$key] = clone $registered[$key];
                $items[$key] = [];
                $addedGroup = true;
            }
        }
        foreach ($groups as $group) {
            $source = self::groupKey($group->getLabel());
            foreach ($group->getItems() as $item) {
                $setting = $settings->get(self::itemKey($item));
                $destination = $setting?->navigation_group;
                if (! $destination || (! isset($templates[$destination]) && ! isset($registered[$destination]))) {
                    $destination = $source;
                }
                if (! isset($templates[$destination])) {
                    $templates[$destination] = clone $registered[$destination];
                    $items[$destination] = [];
                    $addedGroup = true;
                }
                // Move only items already authorized by Filament, preserving their links and children.
                $item = clone $item;
                $item->group($templates[$destination]->getLabel());
                $items[$destination][] = $item;
            }
        }
        if ($addedGroup) {
            $ranks = array_flip(array_keys($registered));
            uksort($templates, fn (string $a, string $b): int => ($a === 'group:ungrouped' ? -1 : ($ranks[$a] ?? count($ranks)))
                <=> ($b === 'group:ungrouped' ? -1 : ($ranks[$b] ?? count($ranks))));
        }
        $result = [];
        foreach ($templates as $key => $group) {
            if ($items[$key] !== [] || str_starts_with($key, SidebarMenu::CUSTOM_GROUP_TARGET_PREFIX)) {
                if ($items[$key] === []) {
                    $group->collapsible(false);
                }
                // Preserve identity separately from editable, translated labels.
                $result[$key] = $group->items($items[$key]);
            }
        }

        return $result;
    }

    /** Order table rows as a sidebar tree: ungrouped links, group heading, then its links. */
    public function orderTableQuery(Builder $query): Builder
    {
        $records = (clone $query)->reorder()->get();
        if ($records->isEmpty()) {
            return $query->reorder()->orderBy($query->getModel()->getQualifiedKeyName());
        }
        $catalog = $this->catalog();
        $groups = $this->orderedGroupKeys($this->sharedSettings());
        $groupRanks = array_flip($groups);
        $sorted = $records->map(function (SidebarMenu $record) use ($catalog, $groupRanks): array {
            $group = $this->groupForRecord($record);

            return [
                'id' => $record->id,
                'group' => $group === 'group:ungrouped' ? -1 : ($groupRanks[$group ?? ''] ?? count($groupRanks)),
                'kind' => ($catalog[$record->target_key]['kind'] ?? null) === 'group' ? 0 : 1,
                'sort' => $record->sort_order,
            ];
        })->sortBy([['group', 'asc'], ['kind', 'asc'], ['sort', 'asc'], ['id', 'asc']]);
        $orderedIds = $sorted->pluck('id')->all();
        $displayPositions = [];
        $positions = [];
        foreach ($sorted as $row) {
            $bucket = $row['kind'] === 0 ? 'headings' : 'menu:'.$row['group'];
            $positions[$bucket] = ($positions[$bucket] ?? 0) + 1;
            $displayPositions[$row['id']] = $positions[$bucket];
        }
        $column = $query->getQuery()->getGrammar()->wrap($query->getModel()->getQualifiedKeyName());
        $cases = [];
        $displayCases = [];
        foreach ($orderedIds as $position => $id) {
            $cases[] = 'when ? then '.$position;
            $displayCases[] = 'when ? then '.$displayPositions[$id];
        }

        // Display positions restart in each shared menu group.
        $query->select($query->getQuery()->columns ?? [$query->getModel()->qualifyColumn('*')]);

        return $query->selectRaw('case '.$column.' '.implode(' ', $displayCases).' end as sidebar_position', $orderedIds)
            ->reorder()->orderByRaw('case '.$column.' '.implode(' ', $cases).' else '.count($orderedIds).' end', $orderedIds);
    }

    private function defaultGroupOrder(): array
    {
        return ['dashboard', 'form_entry', 'cashier', 'candidates', 'form_builder', 'settings', 'activity'];
    }

    public function sharedSettings(): Collection
    {
        return SidebarMenu::query()->navigationOnly()->shared()->get()->keyBy('target_key');
    }

    private function orderedGroupKeys(Collection $settings): array
    {
        $defaults = array_flip(array_map(fn (string $key): string => 'group:'.$key, $this->defaultGroupOrder()));

        return collect($this->catalog())->filter(fn (array $target): bool => $target['kind'] === 'group')
            ->map(fn (array $target, string $key): array => [
                'key' => $key,
                'sort' => $settings->get($key)?->sort_order ?? (isset($defaults[$key]) ? $defaults[$key] + 1 : $target['sort_order']),
                'position' => $defaults[$key] ?? $target['sort_order'],
            ])->sortBy([['sort', 'asc'], ['position', 'asc']])->pluck('key')->values()->all();
    }

    private function reorderBucket(SidebarMenu $record): string
    {
        return ($this->catalog()[$record->target_key]['kind'] ?? null) === 'group'
            ? 'group-headings' : ($this->groupForRecord($record) ?? 'unavailable');
    }

    private function orderedSharedRecords(): Collection
    {
        return $this->orderTableQuery(SidebarMenu::query()->navigationOnly()->shared())->get();
    }

    public function displayPosition(SidebarMenu $record): int
    {
        return (int) ($this->orderedSharedRecords()->firstWhere('target_key', $record->target_key)?->sidebar_position ?? 1);
    }

    public function createGroup(array $data): SidebarMenu
    {
        Gate::authorize('viewAny', SidebarMenu::class);
        Gate::authorize('create', SidebarMenu::class);
        $data = Validator::make($data, [
            'label_en' => ['required', 'string', 'max:255'],
            'label_km' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:1000000'],
        ])->validate();

        return DB::transaction(function () use ($data): SidebarMenu {
            SidebarMenu::query()->where('target_key', 'like', 'group:%')->lockForUpdate()->get();
            $peers = $this->orderedSharedRecords()->filter(fn (SidebarMenu $row): bool => str_starts_with($row->target_key, 'group:'))->values();
            // role_id is legacy storage only; group layout is shared by every role.
            $roleId = Role::query()->where('guard_name', 'web')->whereIn('name', auth()->user()->effectiveRoleNames())->orderBy('id')->value('id')
                ?? Role::query()->where('guard_name', 'web')->orderBy('id')->value('id');
            abort_unless($roleId, 422);
            $group = SidebarMenu::query()->create([
                'role_id' => $roleId,
                'target_key' => SidebarMenu::CUSTOM_GROUP_TARGET_PREFIX.Str::uuid(),
                'label_en' => trim($data['label_en']),
                'label_km' => trim($data['label_km']),
                'sort_order' => $peers->count() + 1,
            ]);
            $this->catalog = null;
            $ordered = $peers->all();
            array_splice($ordered, min((int) $data['sort_order'] - 1, count($ordered)), 0, [$group]);
            foreach ($ordered as $offset => $row) {
                $this->syncLayout($row, ['sort_order' => $offset + 1]);
            }

            return $group->refresh();
        });
    }

    public function updateAtPosition(SidebarMenu $record, array $data): SidebarMenu
    {
        Gate::authorize('viewAny', SidebarMenu::class);
        Gate::authorize('update', $record);

        return DB::transaction(function () use ($record, $data): SidebarMenu {
            $locked = SidebarMenu::query()->navigationOnly()->lockForUpdate()->get()->keyBy('id');
            $current = $locked->get($record->id);
            abort_unless($current, 404);
            $oldBucket = $this->reorderBucket($current);
            $oldPosition = $this->displayPosition($current);
            $position = (int) $data['sort_order'];
            unset($data['sort_order']);
            $data['role_id'] = $current->role_id;
            $data['target_key'] = $current->target_key;
            if (str_starts_with($current->target_key, 'group:')) {
                unset($data['navigation_icon']);
            } elseif (array_key_exists('navigation_icon', $data)) {
                $validated = Validator::make($data, ['navigation_icon' => ['nullable', 'string', 'max:255', Rule::in(array_keys(SidebarMenuIcons::all()))]])->validate();
                $data['navigation_icon'] = filled($validated['navigation_icon'] ?? null) ? $validated['navigation_icon'] : null;
            }
            $current->update($data);
            $this->syncLayout($current, ['navigation_group' => $current->navigation_group, 'navigation_icon' => $current->navigation_icon]);
            $bucket = $this->reorderBucket($current);

            // Saving a name without changing its displayed position must preserve existing ordering.
            if ($oldBucket !== $bucket || $position !== $oldPosition) {
                $peers = $this->orderedSharedRecords()
                    ->filter(fn (SidebarMenu $peer): bool => $peer->target_key !== $current->target_key && $this->reorderBucket($peer) === $bucket)
                    ->pluck('id')->all();
                $index = max(0, min($position - 1, count($peers)));
                array_splice($peers, $index, 0, [$current->id]);
                foreach ($peers as $offset => $id) {
                    $this->syncLayout($locked[$id], ['sort_order' => $offset + 1]);
                }
            }

            return $record->refresh();
        });
    }

    public function groupHasMenus(SidebarMenu $group): bool
    {
        return SidebarMenu::query()->where('navigation_group', $group->target_key)->exists();
    }

    public function deleteGroup(SidebarMenu $record): bool
    {
        Gate::authorize('viewAny', SidebarMenu::class);
        Gate::authorize('delete', $record);

        return DB::transaction(function () use ($record): bool {
            // Serialize with menu moves and remove all legacy role copies of this shared group.
            $records = SidebarMenu::query()->lockForUpdate()->get();
            $current = SidebarMenu::query()->shared()->findOrFail($record->id);
            abort_unless(str_starts_with($current->target_key, SidebarMenu::CUSTOM_GROUP_TARGET_PREFIX), 403);
            Gate::authorize('delete', $current);
            if ($this->groupHasMenus($current)) {
                return false;
            }
            foreach ($records->where('target_key', $current->target_key) as $group) {
                $group->delete();
            }
            SidebarMenuName::query()->where('target_key', $current->target_key)->first()?->delete();
            $this->catalog = null;

            return true;
        });
    }

    public function reorder(array $order, int|string|null $draggedRecordKey = null): void
    {
        Gate::authorize('viewAny', SidebarMenu::class);
        Gate::authorize('reorder', SidebarMenu::class);
        $ids = array_values(array_unique(array_map('strval', $order)));
        abort_if(count($ids) !== count($order) || count($ids) > 1000, 422);
        DB::transaction(function () use ($ids, $draggedRecordKey): void {
            $records = SidebarMenu::query()->navigationOnly()->shared()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            abort_unless($records->count() === count($ids), 422);
            if ($draggedRecordKey !== null) {
                abort_unless($records->has($draggedRecordKey), 422);
                $bucket = $this->reorderBucket($records[$draggedRecordKey]);
                $ids = array_values(array_filter($ids, fn (string $id): bool => $this->reorderBucket($records[$id]) === $bucket));
            }
            $positions = [];
            foreach ($ids as $id) {
                $bucket = $this->reorderBucket($records[$id]);
                $positions[$bucket] = ($positions[$bucket] ?? 0) + 1;
                $this->syncLayout($records[$id], ['sort_order' => $positions[$bucket]]);
            }
        });
    }

    private function syncLayout(SidebarMenu $record, array $layout): void
    {
        $record->update($layout);
        SidebarMenu::query()->where('target_key', $record->target_key)->whereKeyNot($record->id)->update($layout);
    }

    public function populateForRole(int $roleId): int
    {
        // Internal system-menu maintenance; the UI creates only navigation groups.
        abort_unless(auth()->user()?->hasEffectiveRole('admin'), 403);
        Gate::authorize('viewAny', SidebarMenu::class);
        Role::query()->where('guard_name', 'web')->findOrFail($roleId);
        $groupOrder = $this->defaultGroupOrder();
        $groupSort = array_flip(array_map(fn (string $key): string => 'group:'.$key, $groupOrder));

        return DB::transaction(function () use ($roleId, $groupSort): int {
            $created = 0;
            $shared = $this->sharedSettings();
            foreach ($this->catalog() as $key => $target) {
                $layout = $shared->get($key);
                $defaults = ['sort_order' => $layout?->sort_order ?? (isset($groupSort[$key]) ? $groupSort[$key] + 1 : $target['sort_order']),
                    'navigation_group' => $layout?->navigation_group, 'navigation_icon' => $layout?->navigation_icon];
                // Preserve shared renames (including intentionally empty names).
                if (! SidebarMenuName::query()->where('target_key', $key)->exists()) {
                    $defaults['label_en'] = $target['label_en'] ?? null;
                    $defaults['label_km'] = $target['label_km'] ?? null;
                }
                $menu = SidebarMenu::query()->firstOrCreate(['role_id' => $roleId, 'target_key' => $key], $defaults);
                $created += (int) $menu->wasRecentlyCreated;
            }

            return $created;
        });
    }
}
