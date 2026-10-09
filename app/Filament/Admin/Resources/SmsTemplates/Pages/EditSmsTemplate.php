<?php

namespace App\Filament\Admin\Resources\SmsTemplates\Pages;

use App\Filament\Admin\Resources\SmsTemplates\SmsTemplateResource;
use App\Models\SmsTemplate;
use App\Support\PasswordResetOtpSms;
use App\Support\PlasGateSms;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

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

            Action::make('sendTest')
                ->label(__('sms_templates.actions.send_test'))
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->modalDescription(__('sms_templates.send_test.save_first'))
                ->modalSubmitActionLabel(__('sms_templates.actions.send'))
                ->schema([
                    TextInput::make('to')
                        ->label(__('sms_templates.send_test.phone'))
                        ->tel()
                        ->required()
                        ->default(fn (): ?string => auth()->user()?->phone),
                ])
                ->action(function (array $data): void {
                    try {
                        $sent = PlasGateSms::send($data['to'], $this->sample(), ['source' => 'test', 'user_id' => auth()->id()]);
                    } catch (Throwable $exception) {
                        report($exception);
                        $sent = false;
                    }

                    if (! $sent) {
                        Notification::make()->title(__('sms_templates.send_test.failed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('sms_templates.send_test.sent', ['phone' => $data['to']]))->success()->send();
                }),

            Action::make('resetDefaults')
                ->label(__('sms_templates.actions.reset_defaults'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('sms_templates.reset_defaults.description'))
                ->action(function (): void {
                    $this->record->update(SmsTemplate::defaults($this->record->key));
                    $this->fillForm();

                    Notification::make()->title(__('sms_templates.reset_defaults.done'))->success()->send();
                }),
        ];
    }

    /**
     * The saved text with sample code 123456 for the signed-in user.
     */
    private function sample(): string
    {
        return PasswordResetOtpSms::text(auth()->user(), '123456');
    }
}
