<?php

namespace App\Console\Commands;

use App\Models\NewStudentAccount;
use App\Services\AmsStudentAccountTransfer;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReconcileAmsStudentAccount extends Command
{
    protected $signature = 'ams:reconcile-student-account
        {student_id : The AMS student ID already present in New Student Accounts}
        {--apply : Move its matching canonical candidate account}';

    protected $description = 'Preview or complete an existing AMS student account transfer while preserving login and application history';

    public function handle(AmsStudentAccountTransfer $transfer): int
    {
        $account = NewStudentAccount::query()->where('student_id', $this->argument('student_id'))->first();
        if ($account === null) {
            $this->error('No existing New Student Account has this student ID.');

            return self::FAILURE;
        }

        DB::beginTransaction();
        try {
            $moved = $transfer->receive($account->only([
                'student_id', 'email', 'phone', 'first_name_latin', 'last_name_latin',
            ]));
            $sourceId = $moved->auth_id;
            if ($this->option('apply')) {
                DB::commit();
                $this->info($sourceId === null
                    ? 'No matching canonical candidate account required transfer.'
                    : "Transfer complete for student {$moved->student_id}; account identity #{$sourceId}. Login and application history retained.");
            } else {
                DB::rollBack();
                $this->info($sourceId === null
                    ? 'Preview: no matching canonical candidate account.'
                    : "Preview: student {$moved->student_id} links to account identity #{$sourceId}. No changes saved; use --apply to complete the transfer.");
            }
        } catch (DomainException|UniqueConstraintViolationException $exception) {
            DB::rollBack();
            $this->error('Account identity conflict. No records were moved.');

            return self::FAILURE;
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return self::SUCCESS;
    }
}
