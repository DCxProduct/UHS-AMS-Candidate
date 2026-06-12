<?php

namespace Chanthoeun\FilamentDocumentBuilder\Tables\Actions;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class DownloadPdfAction extends Action
{
    protected $templateResolver = null;

    protected $dataResolver = null;

    protected $filenameResolver = null;

    public static function getDefaultName(): ?string
    {
        return 'download_pdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Download PDF');
        $this->icon('heroicon-o-document-arrow-down');
        $this->color('success');

        $this->action(function (Model $record) {

            $templateType = $this->templateResolver ? $this->evaluate($this->templateResolver, ['record' => $record]) : null;
            $template = null;

            if ($templateType instanceof DocumentTemplate) {
                $template = $templateType;
            } elseif (is_string($templateType)) {
                $template = DocumentTemplate::where('type', $templateType)->first();
            }

            if (! $template) {
                $template = DocumentTemplate::first();
            }

            if (! $template) {
                Notification::make()
                    ->title('No Document Template Found')
                    ->danger()
                    ->send();

                return;
            }

            $data = $this->dataResolver ? $this->evaluate($this->dataResolver, ['record' => $record]) : $record;

            $renderer = app(DocumentRenderer::class);
            $pdf = $renderer->render($template, $data);

            $filename = $this->filenameResolver
                ? $this->evaluate($this->filenameResolver, ['record' => $record])
                : 'document-'.now()->format('Y-m-d-His').'.pdf';

            if (! str_ends_with($filename, '.pdf')) {
                $filename .= '.pdf';
            }

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, $filename);
        });
    }

    public function templateType(\Closure|string $resolver): static
    {
        $this->templateResolver = $resolver;

        return $this;
    }

    public function template(\Closure|DocumentTemplate $resolver): static
    {
        $this->templateResolver = $resolver;

        return $this;
    }

    public function recordData(\Closure $resolver): static
    {
        $this->dataResolver = $resolver;

        return $this;
    }

    public function filename(\Closure|string $resolver): static
    {
        $this->filenameResolver = $resolver;

        return $this;
    }
}
