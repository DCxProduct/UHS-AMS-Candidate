<?php

namespace App\Filament\Admin\Resources\MessagingSettings\Pages;

use App\Filament\Admin\Resources\MessagingSettings\MessagingSettingResource;
use App\Models\MessagingSetting;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * @property MessagingSetting $record
 */
class EditMessagingSetting extends EditRecord
{
    protected static string $resource = MessagingSettingResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('messaging_settings.resource_label');
    }

    public function getBreadcrumb(): string
    {
        return __('messaging_settings.resource_label');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // An emptied password or key is removed, so the .env value is used again.
        foreach (MessagingSetting::SECRETS as $column) {
            if (blank($data[$column] ?? null)) {
                $data[$column] = null;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        MessagingSetting::applyToConfig();

        // Queue workers read the settings when they start; restart them to use the new ones.
        try {
            Artisan::call('queue:restart');
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->fillForm();
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
