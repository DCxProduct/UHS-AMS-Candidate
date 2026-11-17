<?php

namespace App\Filament\Admin\Resources\NewStudentAccounts\Pages;

use App\Filament\Admin\Resources\NewStudentAccounts\NewStudentAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditNewStudentAccount extends EditRecord
{
    protected static string $resource = NewStudentAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            if (array_key_exists('is_active', $data) && (bool) $data['is_active'] !== (bool) $locked->is_active) {
                $action = (bool) $data['is_active'] ? 'activateAccount' : 'deactivateAccount';
                abort_unless(NewStudentAccountResource::can($action, $locked), 403);
            }

            // Keep Filament's page record current for its save events and form.
            $record->setRawAttributes($locked->getAttributes(), sync: true);

            return parent::handleRecordUpdate($record, $data);
        });
    }
}
