<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Pages;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Support\ResetPasswordEmail;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * @property EmailTemplate $record
 */
class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('email_templates.actions.preview'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading(fn (): string => $this->record->label())
                ->modalDescription(__('email_templates.send_test.save_first'))
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('email_templates.actions.close'))
                ->modalContent(fn () => view('filament.admin.email-templates.preview', [
                    'previews' => collect(EmailTemplate::LOCALES)->mapWithKeys(fn (string $locale): array => [
                        __('email_templates.tabs.'.$locale) => (string) $this->sample($locale)->render(),
                    ])->all(),
                ])),

            Action::make('sendTest')
                ->label(__('email_templates.actions.send_test'))
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->modalDescription(__('email_templates.send_test.save_first'))
                ->modalSubmitActionLabel(__('email_templates.actions.send'))
                ->schema([
                    TextInput::make('to')
                        ->label(__('email_templates.send_test.email'))
                        ->email()
                        ->required()
                        ->default(fn (): ?string => auth()->user()?->email),
                    Select::make('locale')
                        ->label(__('email_templates.send_test.language'))
                        ->options(collect(EmailTemplate::LOCALES)->mapWithKeys(fn (string $locale): array => [$locale => __('email_templates.tabs.'.$locale)])->all())
                        ->default(app()->getLocale() === 'km' ? 'km' : 'en')
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    try {
                        $message = $this->sample($data['locale']);
                        // Sent like the real email, so the logo is embedded the same way.
                        Mail::send($message->view, $message->viewData, fn ($mail) => $mail->to($data['to'])->subject((string) $message->subject));
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->title(__('email_templates.send_test.failed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('email_templates.send_test.sent', ['email' => $data['to']]))->success()->send();
                }),

            Action::make('resetDefaults')
                ->label(__('email_templates.actions.reset_defaults'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('email_templates.reset_defaults.description'))
                ->action(function (): void {
                    $this->record->update(EmailTemplate::defaults($this->record->key));
                    $this->fillForm();

                    Notification::make()->title(__('email_templates.reset_defaults.done'))->success()->send();
                }),
        ];
    }

    /**
     * The saved template, filled with sample data for the signed-in user.
     */
    private function sample(string $locale): MailMessage
    {
        $user = auth()->user();

        return ResetPasswordEmail::build(
            $user,
            route('student.password.reset', ['token' => 'sample-token', 'email' => $user?->email]),
            $locale,
        );
    }
}
