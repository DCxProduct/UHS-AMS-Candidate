<?php

namespace App\Models;

use App\Support\WorkflowButtonColors;
use App\Support\WorkflowStageTypeSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** A reusable button presentation backed by an existing workflow action. */
class WorkflowButton extends Model
{
    protected $fillable = ['label_en', 'label_km', 'action_key', 'icon', 'color', 'is_active'];

    protected $attributes = [
        'icon' => 'heroicon-o-cursor-arrow-rays',
        'color' => 'primary',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $button): void {
            $button->label_en = trim((string) $button->label_en);
            $button->label_km = filled($button->label_km) ? trim((string) $button->label_km) : null;

            if ($button->exists && $button->isDirty('action_key') && $button->inUse()) {
                throw ValidationException::withMessages([
                    'action_key' => __('workflow_buttons.validation.action_in_use'),
                ]);
            }

            Validator::make($button->getAttributes(), [
                'label_en' => ['required', 'string', 'max:255'],
                'label_km' => ['nullable', 'string', 'max:255'],
                'action_key' => ['required', 'string', Rule::in(array_keys(self::actionOptions()))],
                'icon' => ['required', 'string', Rule::in(array_keys(self::iconOptions()))],
                'color' => ['required', 'string', Rule::in(array_keys(self::colorOptions()))],
                'is_active' => ['required', 'boolean'],
            ])->validate();
        });

        static::deleting(function (self $button): void {
            if (! $button->canDelete()) {
                throw ValidationException::withMessages([
                    'workflow_button' => __('workflow_buttons.validation.delete_protected'),
                ]);
            }
        });
    }

    public function label(): string
    {
        return in_array(app()->getLocale(), ['km', 'kh'], true)
            ? (string) ($this->label_km ?: $this->label_en)
            : (string) $this->label_en;
    }

    public function behavior(): ?string
    {
        return WorkflowStageTypeSettings::buttonActions()[$this->action_key]['behavior'] ?? null;
    }

    public function inUse(): bool
    {
        if (! $this->exists || ! Schema::hasTable('workflow_stage_types')
            || ! Schema::hasColumn('workflow_stage_types', 'workflow_button_ids')) {
            return false;
        }

        return WorkflowStageType::query()->get(['workflow_button_ids'])->contains(function (WorkflowStageType $type): bool {
            $ids = $type->getRawOriginal('workflow_button_ids');
            $ids = is_string($ids) ? json_decode($ids, true) : $ids;

            return is_array($ids) && in_array((string) $this->getKey(), array_map('strval', $ids), true);
        });
    }

    public function canDelete(): bool
    {
        return ! $this->inUse();
    }

    /** Copy these values into a stage so later button edits cannot change an application. */
    public function toDefinition(): array
    {
        return [
            'id' => (int) $this->getKey(),
            'label_en' => (string) $this->label_en,
            'label_km' => $this->label_km,
            'action_key' => (string) $this->action_key,
            'icon' => (string) $this->icon,
            'color' => (string) $this->color,
        ];
    }

    public static function actionOptions(): array
    {
        return array_map(fn (array $action): string => $action['label'], WorkflowStageTypeSettings::buttonActions());
    }

    public static function iconOptions(): array
    {
        $icons = [
            'heroicon-o-cursor-arrow-rays' => 'action',
            'heroicon-o-pencil-square' => 'edit',
            'heroicon-o-paper-airplane' => 'submit',
            'heroicon-o-check-circle' => 'check',
            'heroicon-o-arrow-uturn-left' => 'return',
            'heroicon-o-x-circle' => 'reject',
            'heroicon-o-shield-check' => 'verify',
            'heroicon-o-banknotes' => 'payment',
            'heroicon-o-bell-alert' => 'notify',
            'heroicon-o-eye' => 'view',
            'heroicon-o-pencil' => 'pencil',
            'heroicon-o-document' => 'document',
            'heroicon-o-document-check' => 'document_check',
            'heroicon-o-document-text' => 'document_text',
            'heroicon-o-document-plus' => 'document_plus',
            'heroicon-o-document-minus' => 'document_minus',
            'heroicon-o-document-duplicate' => 'document_duplicate',
            'heroicon-o-clipboard' => 'clipboard',
            'heroicon-o-clipboard-document' => 'clipboard_document',
            'heroicon-o-clipboard-document-check' => 'clipboard_check',
            'heroicon-o-clipboard-document-list' => 'clipboard_list',
            'heroicon-o-folder' => 'folder',
            'heroicon-o-folder-open' => 'folder_open',
            'heroicon-o-folder-plus' => 'folder_plus',
            'heroicon-o-archive-box' => 'archive',
            'heroicon-o-inbox' => 'inbox',
            'heroicon-o-inbox-arrow-down' => 'inbox_receive',
            'heroicon-o-arrow-down-tray' => 'download',
            'heroicon-o-arrow-up-tray' => 'upload',
            'heroicon-o-printer' => 'print',
            'heroicon-o-calendar' => 'calendar',
            'heroicon-o-calendar-days' => 'calendar_days',
            'heroicon-o-clock' => 'clock',
            'heroicon-o-arrow-path' => 'refresh',
            'heroicon-o-arrow-right' => 'arrow_right',
            'heroicon-o-arrow-left' => 'arrow_left',
            'heroicon-o-arrow-up' => 'arrow_up',
            'heroicon-o-arrow-down' => 'arrow_down',
            'heroicon-o-arrow-right-circle' => 'next',
            'heroicon-o-arrow-left-circle' => 'previous',
            'heroicon-o-chevron-right' => 'chevron_right',
            'heroicon-o-chevron-left' => 'chevron_left',
            'heroicon-o-chevron-double-right' => 'skip_forward',
            'heroicon-o-chevron-double-left' => 'skip_back',
            'heroicon-o-arrows-right-left' => 'transfer',
            'heroicon-o-user' => 'user',
            'heroicon-o-user-circle' => 'user_profile',
            'heroicon-o-user-plus' => 'add_user',
            'heroicon-o-user-minus' => 'remove_user',
            'heroicon-o-user-group' => 'team',
            'heroicon-o-users' => 'users',
            'heroicon-o-academic-cap' => 'education',
            'heroicon-o-identification' => 'identification',
            'heroicon-o-building-office' => 'office',
            'heroicon-o-building-library' => 'institution',
            'heroicon-o-globe-alt' => 'globe',
            'heroicon-o-map-pin' => 'location',
            'heroicon-o-home' => 'home',
            'heroicon-o-envelope' => 'email',
            'heroicon-o-envelope-open' => 'email_open',
            'heroicon-o-chat-bubble-left' => 'message',
            'heroicon-o-chat-bubble-left-right' => 'conversation',
            'heroicon-o-phone' => 'phone',
            'heroicon-o-bell' => 'bell',
            'heroicon-o-megaphone' => 'announcement',
            'heroicon-o-currency-dollar' => 'currency',
            'heroicon-o-credit-card' => 'credit_card',
            'heroicon-o-receipt-percent' => 'receipt',
            'heroicon-o-calculator' => 'calculator',
            'heroicon-o-chart-bar' => 'bar_chart',
            'heroicon-o-chart-pie' => 'pie_chart',
            'heroicon-o-table-cells' => 'table',
            'heroicon-o-presentation-chart-line' => 'presentation',
            'heroicon-o-lock-closed' => 'lock',
            'heroicon-o-lock-open' => 'unlock',
            'heroicon-o-key' => 'key',
            'heroicon-o-shield-exclamation' => 'security_alert',
            'heroicon-o-finger-print' => 'fingerprint',
            'heroicon-o-eye-slash' => 'hide',
            'heroicon-o-cog-6-tooth' => 'settings',
            'heroicon-o-adjustments-horizontal' => 'adjustments',
            'heroicon-o-wrench-screwdriver' => 'tools',
            'heroicon-o-plus' => 'add',
            'heroicon-o-minus' => 'remove',
            'heroicon-o-plus-circle' => 'add_circle',
            'heroicon-o-minus-circle' => 'remove_circle',
            'heroicon-o-check' => 'checkmark',
            'heroicon-o-x-mark' => 'close',
            'heroicon-o-exclamation-triangle' => 'warning',
            'heroicon-o-information-circle' => 'information',
            'heroicon-o-question-mark-circle' => 'help',
            'heroicon-o-no-symbol' => 'blocked',
            'heroicon-o-star' => 'star',
            'heroicon-o-heart' => 'heart',
            'heroicon-o-flag' => 'flag',
            'heroicon-o-bolt' => 'bolt',
            'heroicon-o-magnifying-glass' => 'search',
            'heroicon-o-link' => 'link',
            'heroicon-o-bookmark' => 'bookmark',
            'heroicon-o-trash' => 'delete',
        ];

        return array_map(fn (string $key): string => __('workflow_buttons.icons.'.$key), $icons);
    }

    public static function colorOptions(): array
    {
        $colors = array_merge(
            ['primary', 'success', 'warning', 'danger', 'info', 'gray'],
            array_keys(WorkflowButtonColors::palettes()),
        );

        return array_combine($colors, array_map(fn (string $color): string => __('workflow_buttons.colors.'.$color), $colors));
    }

    /** Keep inactive selections visible when reopening an existing stage. */
    public static function selectOptions(mixed $selected = []): array
    {
        if (! Schema::hasTable('workflow_buttons')) {
            return [];
        }

        $selected = is_array($selected) ? $selected : [$selected];
        $ids = array_values(array_filter($selected, fn (mixed $id): bool => (is_int($id) || is_string($id)) && ctype_digit((string) $id) && (int) $id > 0));

        return static::query()
            ->where(fn (Builder $query): Builder => $query->where('is_active', true)->orWhereIn('id', $ids))
            ->orderBy('label_en')
            ->get()
            ->mapWithKeys(fn (self $button): array => [
                $button->getKey() => $button->label().($button->is_active ? '' : ' ('.__('workflow_buttons.states.inactive').')'),
            ])
            ->all();
    }
}
