<?php

namespace App\Filament\Admin\Resources\ReviewApplications\Pages;

use App\Filament\Admin\Resources\ReviewApplications\ReviewApplicationResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListReviewApplications extends ListRecords
{
    protected static string $resource = ReviewApplicationResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('review_applications.list_title');
    }

    public function getBreadcrumb(): string
    {
        return __('review_applications.breadcrumb_list');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
