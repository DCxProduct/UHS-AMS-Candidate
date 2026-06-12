<?php

namespace Chanthoeun\FilamentDocumentBuilder\Actions;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DownloadAllPdfAction extends Action
{
    protected $templateResolver = null;

    protected $recordsResolver = null;

    protected $filenameResolver = null;

    public static function getDefaultName(): ?string
    {
        return 'download_all_pdf';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Download PDF');
        $this->icon('heroicon-o-document-arrow-down');
        $this->color('success');

        $this->action(function () {
            $records = $this->recordsResolver ? $this->evaluate($this->recordsResolver) : collect([]);

            if ((is_countable($records) ? count($records) : 0) === 0) {
                Notification::make()
                    ->title('No records to export')
                    ->warning()
                    ->send();

                return;
            }

            $templateType = $this->templateResolver ? $this->evaluate($this->templateResolver) : null;
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

            $renderer = app(DocumentRenderer::class);

            $pdf = $renderer->renderMultiple($template, $records);

            $filename = $this->filenameResolver
                ? $this->evaluate($this->filenameResolver)
                : 'documents-'.now()->format('Y-m-d-His').'.pdf';

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

    public function records(\Closure|iterable $resolver): static
    {
        $this->recordsResolver = $resolver;

        return $this;
    }

    public function filename(\Closure|string $resolver): static
    {
        $this->filenameResolver = $resolver;

        return $this;
    }
}
