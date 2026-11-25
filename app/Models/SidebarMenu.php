<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema;

class SidebarMenu extends Model
{
    public const CUSTOM_FORM_TARGET_PREFIX = 'item:custom-form-entry-';

    public const CUSTOM_GROUP_TARGET_PREFIX = 'group:custom-';

    protected $fillable = ['role_id', 'target_key', 'navigation_group', 'navigation_icon', 'label_en', 'label_km', 'sort_order'];

    public function scopeNavigationOnly(Builder $query): Builder
    {
        return $query->where('target_key', 'not like', self::CUSTOM_FORM_TARGET_PREFIX.'%');
    }

    public function scopeShared(Builder $query): Builder
    {
        // Keep one stable editable row for each menu/group; legacy role rows remain intact.
        return $query->whereIn($this->getQualifiedKeyName(), fn (QueryBuilder $subquery): QueryBuilder => $subquery
            ->selectRaw('MIN(id)')->from($this->getTable())->groupBy('target_key'));
    }

    protected function casts(): array
    {
        return ['role_id' => 'integer', 'sort_order' => 'integer'];
    }

    public function save(array $options = [])
    {
        // A global rename and the role's configuration must commit together.
        return $this->getConnection()->transaction(fn () => parent::save($options));
    }

    protected static function booted(): void
    {
        static::saving(function (SidebarMenu $menu): void {
            if (! Schema::hasTable('sidebar_menu_names')) {
                return;
            }

            $name = SidebarMenuName::query()->where('target_key', $menu->target_key)->lockForUpdate()->first();
            $changed = $menu->isDirty(['label_en', 'label_km']);
            if ($changed) {
                $name ??= new SidebarMenuName(['target_key' => $menu->target_key]);
                foreach (['label_en', 'label_km'] as $field) {
                    if ($menu->isDirty($field) || (! $menu->exists && array_key_exists($field, $menu->getAttributes()))) {
                        $name->$field = filled($menu->$field) ? trim($menu->$field) : null;
                    }
                }
                $name->save();
                // Keep the resource's role rows readable/searchable using the shared name.
                // Query updates do not trigger recursive model events or reorder other roles.
                static::query()->where('target_key', $menu->target_key)->update([
                    'label_en' => $name->label_en, 'label_km' => $name->label_km,
                ]);
            }

            if ($name) {
                $menu->fill(['label_en' => $name->label_en, 'label_km' => $name->label_km]);
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function label(string $fallback): string
    {
        $label = app()->getLocale() === 'km' ? $this->label_km : $this->label_en;

        return filled($label) ? $label : $fallback;
    }
}
