<?php

namespace App\Filament\Admin\Resources\GuideBooks\Pages;

use App\Filament\Admin\Resources\GuideBooks\GuideBookResource;
use App\Models\GuideBook;
use Filament\Resources\Pages\Page;

class ManageGuideBook extends Page
{
    protected static string $resource = GuideBookResource::class;

    protected string $view = 'filament.resources.guide-books.manage';

    public function mount(): void
    {
        $resource = static::getResource();
        abort_unless($resource::canViewAny(), 403);
        $guide = $resource::getEloquentQuery()->orderBy('id')->first();

        if (! $guide) {
            abort_unless($resource::canEdit(new GuideBook), 403);
            $guide = GuideBook::ensureDefault();
        }

        if ($resource::canEdit($guide)) {
            $this->redirect($resource::getUrl('edit', ['record' => $guide]));

            return;
        }

        abort_unless($resource::canView($guide), 403);
        $this->redirect($resource::getUrl('view', ['record' => $guide]));
    }
}
