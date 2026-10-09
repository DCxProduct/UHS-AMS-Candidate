<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Pages;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Support\ResetPasswordEmail;
use App\Support\TemplateEmail;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;

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
                    'previews' => [$this->record->label() => (string) $this->sample()->render()],
                ])),

            DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->isBuiltIn()),
        ];
    }

    /**
     * The saved template, filled with sample data for the signed-in user.
     */
    private function sample(): MailMessage
    {
        $user = auth()->user();

        if ($this->record->key !== EmailTemplate::RESET_PASSWORD) {
            return TemplateEmail::build($this->record, $user, collect(['form', 'stage', 'message', 'status'])
                ->mapWithKeys(fn (string $name): array => [$name => __('email_templates.built_in.samples.'.$name)])
                ->all());
        }

        return ResetPasswordEmail::build(
            $user,
            route('student.password.reset', ['token' => 'sample-token', 'email' => $user?->email]),
        );
    }
}
