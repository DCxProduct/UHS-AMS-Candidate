<?php

namespace App\Filament\Admin\Resources\ExitExamResults\Pages;

use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExitExamResults\ExitExamResultResource;
use Illuminate\Contracts\Support\Htmlable;

class ListExitExamResults extends ListExamResults
{
    protected static string $resource = ExitExamResultResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('exit_exam_results.list_title');
    }

    public function getBreadcrumb(): string
    {
        return __('exit_exam_results.breadcrumb_list');
    }
}
