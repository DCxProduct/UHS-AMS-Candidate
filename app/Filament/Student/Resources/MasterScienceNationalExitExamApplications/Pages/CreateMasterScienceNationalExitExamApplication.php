<?php

namespace App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\Pages;

use App\Filament\Student\Resources\MasterScienceNationalExitExamApplications\MasterScienceNationalExitExamApplicationResource;
use App\Models\MasterScienceNationalExitExamApplication;
use Filament\Resources\Pages\CreateRecord;

class CreateMasterScienceNationalExitExamApplication extends CreateRecord
{
    protected static string $resource = MasterScienceNationalExitExamApplicationResource::class;

    public function getTitle(): string
    {
        return __('master_science_national_exit_exam_applications.pages.create_title');
    }

    public function getHeading(): string
    {
        return __('master_science_national_exit_exam_applications.pages.create_heading');
    }

    public function getBreadcrumb(): string
    {
        return __('master_science_national_exit_exam_applications.actions.create');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userId = auth()->id();

        $data['user_id'] = $data['user_id'] ?? $userId;
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;

        $data['exam_type'] = 'national_exit_exam';
        $data['degree_level'] = 'master_science';
        $data['training_course'] = 'Master of Science';

        $data['status'] = $data['status'] ?? 'draft';
        $data['nationality'] = $data['nationality'] ?? 'ខ្មែរ';
        $data['citizenship'] = $data['citizenship'] ?? 'ខ្មែរ';

        if (blank($data['application_no'] ?? null)) {
            $nextId = (int) (MasterScienceNationalExitExamApplication::query()->max('id') ?? 0) + 1;

            $data['application_no'] = 'MSC-NEE-' . now()->format('Y') . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
        }

        if (blank($data['receipt_no'] ?? null)) {
            $data['receipt_no'] = 'REC-MSC-NEE-' . now()->format('YmdHis');
        }

        foreach ([
            'children',
            'siblings',
            'education_histories',
            'work_histories',
            'document_checklist',
            'extra_data',
        ] as $jsonField) {
            if (! isset($data[$jsonField]) || ! is_array($data[$jsonField])) {
                $data[$jsonField] = [];
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return MasterScienceNationalExitExamApplicationResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('master_science_national_exit_exam_applications.notifications.created');
    }
}
