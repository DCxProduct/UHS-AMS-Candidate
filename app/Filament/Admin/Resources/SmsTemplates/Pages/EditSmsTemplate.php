<?php

namespace App\Filament\Admin\Resources\SmsTemplates\Pages;

use App\Filament\Admin\Resources\SmsTemplates\SmsTemplateResource;
use App\Models\SmsTemplate;
use App\Support\PasswordResetOtpSms;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property SmsTemplate $record
 */
class EditSmsTemplate extends EditRecord
{
    protected static string $resource = SmsTemplateResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('sms_templates.actions.preview'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading(fn (): string => $this->record->label())
                ->modalDescription(__('sms_templates.send_test.save_first'))
                ->modalWidth('lg')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('sms_templates.actions.close'))
                ->modalContent(fn () => view('filament.admin.sms-templates.preview', [
                    'previews' => [$this->record->label() => $this->sample()],
                ])),

            DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->isBuiltIn()),
        ];
    }

    /**
     * The saved text with sample code 123456 for the signed-in user.
     */
    private function sample(): string
    {
        if ($this->record->key !== SmsTemplate::RESET_PASSWORD_OTP) {
            return $this->record->renderFor(auth()->user(), collect(['form', 'stage', 'message', 'status'])
                ->mapWithKeys(fn (string $name): array => [$name => __('sms_templates.built_in.samples.'.$name)])
                ->all());
        }

        return PasswordResetOtpSms::text(auth()->user(), '123456');
    }
}
