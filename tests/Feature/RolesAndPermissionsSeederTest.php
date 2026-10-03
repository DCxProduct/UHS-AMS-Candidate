<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_and_registrar_roles_are_not_seeded(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(0);

        (new RolesAndPermissionsSeeder)->run();

        $this->assertFalse(
            Role::query()->whereIn('name', ['cashier', 'registrar'])->exists(),
        );
    }

    public function test_requested_staff_roles_are_seeded_with_cashier_permissions(): void
    {
        $deletePermissions = [
            'ViewAny:CustomFormEntry',
            'Delete:CustomFormEntry',
            'ViewAny:UnpaidApplication',
            'ClearData:UnpaidApplication',
            'ViewAny:Payment',
            'ClearData:Payment',
            'ViewAny:CandidateEntranceStatistic',
            'ClearData:CandidateEntranceStatistic',
            'ViewAny:ExamResult',
            'ClearData:ExamResult',
            'ViewAny:ExitExamResult',
            'ClearData:ExitExamResult',
            'ViewAny:CustomForm',
            'Delete:CustomForm',
            'ViewAny:DocumentTemplate',
            'Delete:DocumentTemplate',
            'ViewAny:ClosingDate',
            'Delete:ClosingDate',
            'ViewAny:DegreeLevel',
            'Delete:DegreeLevel',
            'ViewAny:UserType',
            'Delete:UserType',
            'ViewAny:CandidateList',
            'Delete:CandidateList',
            'ViewAny:CandidateExitStatistic',
            'ClearData:CandidateExitStatistic',
            'ViewAny:PaymentType',
            'Delete:PaymentType',
            'ViewAny:ExchangeRate',
            'ViewAny:CandidateSubmitPopupSetting',
            'ViewAny:GeoLocation',
            'Delete:GeoLocation',
            'ViewAny:SystemUser',
            'Delete:SystemUser',
            'ViewAny:RoleType',
            'Delete:RoleType',
            'ViewAny:Role',
            'Delete:Role',
            'ViewAny:AuditLog',
            'ClearData:AuditLog',
        ];
        $viewerPermissions = [
            'ViewAny:CustomFormEntry',
            'ViewAny:UnpaidApplication',
            'ViewAny:Payment',
            'ViewAny:CandidateEntranceStatistic',
            'ViewAny:ExamResult',
            'ViewAny:ExitExamResult',
            'ViewAny:CustomForm',
            'ViewAny:DocumentTemplate',
            'ViewAny:ClosingDate',
            'ViewAny:DegreeLevel',
            'ViewAny:UserType',
            'ViewAny:CandidateList',
            'ViewAny:CandidateExitStatistic',
            'ViewAny:PaymentType',
            'ViewAny:ExchangeRate',
            'ViewAny:CandidateSubmitPopupSetting',
            'ViewAny:GeoLocation',
            'ViewAny:SystemUser',
            'ViewAny:RoleType',
            'ViewAny:Role',
            'ViewAny:AuditLog',
        ];
        $dataEntryPermissions = [
            'ViewAny:CustomFormEntry',
            'Create:CustomFormEntry',
            'Update:CustomFormEntry',
            'View:CustomFormEntry',
            'DownloadExcel:CustomFormEntry',
            'ClearData:CustomFormEntry',
            'EditReviewNote:CustomFormEntry',
            'ViewPdf:CustomFormEntry',
            'Accepted:CustomFormEntry',
            'Rejected:CustomFormEntry',
            'DownloadPdf:CustomFormEntry',
            'ViewAny:UnpaidApplication',
            'Pay:UnpaidApplication',
            'DownloadExcel:UnpaidApplication',
            'ViewAny:Payment',
            'Update:Payment',
            'ViewSlip:Payment',
            'DownloadExcel:Payment',
            'ViewAny:CandidateEntranceStatistic',
            'Passed:CandidateEntranceStatistic',
            'Pending:CandidateEntranceStatistic',
            'BulkPassed:CandidateEntranceStatistic',
            'BulkPending:CandidateEntranceStatistic',
            'DownloadExcel:CandidateEntranceStatistic',
            'ViewAny:CandidateExitStatistic',
            'Passed:CandidateExitStatistic',
            'Pending:CandidateExitStatistic',
            'BulkPassed:CandidateExitStatistic',
            'BulkPending:CandidateExitStatistic',
            'DownloadExcel:CandidateExitStatistic',
            'ViewAny:ClosingDate',
            'Create:ClosingDate',
            'Update:ClosingDate',
            'ViewAny:DegreeLevel',
            'Create:DegreeLevel',
            'Update:DegreeLevel',
            'ViewAny:UserType',
            'Create:UserType',
            'Update:UserType',
            'ViewAny:ExamResult',
            'NotifyStudent:ExamResult',
            'NotifyAllStudents:ExamResult',
            'DownloadExcel:ExamResult',
            'ViewAny:ExitExamResult',
            'NotifyStudent:ExitExamResult',
            'NotifyAllStudents:ExitExamResult',
            'DownloadExcel:ExitExamResult',
            'ViewAny:CustomForm',
            'Create:CustomForm',
            'Update:CustomForm',
            'EditTemplate:CustomForm',
            'ViewAny:DocumentTemplate',
            'Update:DocumentTemplate',
            'ViewAny:CandidateList',
            'Create:CandidateList',
            'Update:CandidateList',
            'ViewAny:CandidateExitStatistic',
            'ViewAny:PaymentType',
            'Create:PaymentType',
            'Update:PaymentType',
            'ViewAny:ExchangeRate',
            'Update:ExchangeRate',
            'ViewAny:CandidateSubmitPopupSetting',
            'Update:CandidateSubmitPopupSetting',
        ];
        $crudPermissions = [
            'ViewAny:CustomFormEntry',
            'Create:CustomFormEntry',
            'Update:CustomFormEntry',
            'Delete:CustomFormEntry',
            'View:CustomFormEntry',
            'DownloadExcel:CustomFormEntry',
            'ClearData:CustomFormEntry',
            'EditReviewNote:CustomFormEntry',
            'ViewPdf:CustomFormEntry',
            'Accepted:CustomFormEntry',
            'Rejected:CustomFormEntry',
            'DownloadPdf:CustomFormEntry',
            'ViewAny:UnpaidApplication',
            'Pay:UnpaidApplication',
            'DownloadExcel:UnpaidApplication',
            'ClearData:UnpaidApplication',
            'ViewAny:Payment',
            'Update:Payment',
            'ViewSlip:Payment',
            'DownloadExcel:Payment',
            'ClearData:Payment',
            'ViewAny:CandidateEntranceStatistic',
            'Passed:CandidateEntranceStatistic',
            'Pending:CandidateEntranceStatistic',
            'BulkPassed:CandidateEntranceStatistic',
            'BulkPending:CandidateEntranceStatistic',
            'DownloadExcel:CandidateEntranceStatistic',
            'ClearData:CandidateEntranceStatistic',
            'ViewAny:ExamResult',
            'NotifyStudent:ExamResult',
            'NotifyAllStudents:ExamResult',
            'DownloadExcel:ExamResult',
            'ClearData:ExamResult',
            'ViewAny:ExitExamResult',
            'NotifyStudent:ExitExamResult',
            'NotifyAllStudents:ExitExamResult',
            'DownloadExcel:ExitExamResult',
            'ClearData:ExitExamResult',
            'ViewAny:CustomForm',
            'Create:CustomForm',
            'Update:CustomForm',
            'Delete:CustomForm',
            'EditTemplate:CustomForm',
            'ViewAny:DocumentTemplate',
            'Update:DocumentTemplate',
            'Delete:DocumentTemplate',
            'ViewAny:ClosingDate',
            'Create:ClosingDate',
            'Update:ClosingDate',
            'Delete:ClosingDate',
            'ViewAny:DegreeLevel',
            'Create:DegreeLevel',
            'Update:DegreeLevel',
            'Delete:DegreeLevel',
            'ViewAny:UserType',
            'Create:UserType',
            'Update:UserType',
            'Delete:UserType',
            'ViewAny:CandidateList',
            'Create:CandidateList',
            'Update:CandidateList',
            'Delete:CandidateList',
            'ViewAny:CandidateExitStatistic',
            'Passed:CandidateExitStatistic',
            'Pending:CandidateExitStatistic',
            'BulkPassed:CandidateExitStatistic',
            'BulkPending:CandidateExitStatistic',
            'DownloadExcel:CandidateExitStatistic',
            'ClearData:CandidateExitStatistic',
            'ViewAny:PaymentType',
            'Create:PaymentType',
            'Update:PaymentType',
            'Delete:PaymentType',
            'ViewAny:ExchangeRate',
            'Update:ExchangeRate',
            'ViewAny:CandidateSubmitPopupSetting',
            'Update:CandidateSubmitPopupSetting',
            'ViewAny:GeoLocation',
            'Create:GeoLocation',
            'Update:GeoLocation',
            'Delete:GeoLocation',
            'ViewAny:SystemUser',
            'Create:SystemUser',
            'Update:SystemUser',
            'Delete:SystemUser',
            'ViewAny:RoleType',
            'Create:RoleType',
            'Update:RoleType',
            'Delete:RoleType',
            'ViewAny:Role',
            'Create:Role',
            'Update:Role',
            'Delete:Role',
            'ViewAny:AuditLog',
            'ClearData:AuditLog',
        ];
        $cashierManagerPermissions = [
            'ViewAny:PaymentType',
            'Create:PaymentType',
            'Update:PaymentType',
            'Delete:PaymentType',
            'ViewAny:ExchangeRate',
            'Update:ExchangeRate',
            'ViewAny:UnpaidApplication',
            'Pay:UnpaidApplication',
            'DownloadExcel:UnpaidApplication',
            'ClearData:UnpaidApplication',
            'ViewAny:Payment',
            'Update:Payment',
            'ViewSlip:Payment',
            'DownloadExcel:Payment',
            'ClearData:Payment',
        ];
        $cashierOfficerPermissions = [
            'ViewAny:UnpaidApplication',
            'Pay:UnpaidApplication',
            'DownloadExcel:UnpaidApplication',
            'ClearData:UnpaidApplication',
            'ViewAny:Payment',
            'Update:Payment',
            'ViewSlip:Payment',
            'DownloadExcel:Payment',
            'ClearData:Payment',
        ];
        $registrarManagerPermissions = [
            'ViewAny:CustomFormEntry',
            'Create:CustomFormEntry',
            'Update:CustomFormEntry',
            'Delete:CustomFormEntry',
            'View:CustomFormEntry',
            'DownloadExcel:CustomFormEntry',
            'ClearData:CustomFormEntry',
            'EditReviewNote:CustomFormEntry',
            'ViewPdf:CustomFormEntry',
            'Accepted:CustomFormEntry',
            'Rejected:CustomFormEntry',
            'DownloadPdf:CustomFormEntry',
            'ViewAny:CandidateEntranceStatistic',
            'Passed:CandidateEntranceStatistic',
            'Pending:CandidateEntranceStatistic',
            'BulkPassed:CandidateEntranceStatistic',
            'BulkPending:CandidateEntranceStatistic',
            'DownloadExcel:CandidateEntranceStatistic',
            'ClearData:CandidateEntranceStatistic',
            'ViewAny:CandidateExitStatistic',
            'Passed:CandidateExitStatistic',
            'Pending:CandidateExitStatistic',
            'BulkPassed:CandidateExitStatistic',
            'BulkPending:CandidateExitStatistic',
            'DownloadExcel:CandidateExitStatistic',
            'ClearData:CandidateExitStatistic',
            'ViewAny:ExamResult',
            'NotifyStudent:ExamResult',
            'NotifyAllStudents:ExamResult',
            'DownloadExcel:ExamResult',
            'ClearData:ExamResult',
            'ViewAny:ExitExamResult',
            'NotifyStudent:ExitExamResult',
            'NotifyAllStudents:ExitExamResult',
            'DownloadExcel:ExitExamResult',
            'ClearData:ExitExamResult',
            'ViewAny:CustomForm',
            'Create:CustomForm',
            'Update:CustomForm',
            'Delete:CustomForm',
            'EditTemplate:CustomForm',
            'ViewAny:DocumentTemplate',
            'Update:DocumentTemplate',
            'Delete:DocumentTemplate',
            'ViewAny:ClosingDate',
            'Create:ClosingDate',
            'Update:ClosingDate',
            'Delete:ClosingDate',
            'ViewAny:DegreeLevel',
            'Create:DegreeLevel',
            'Update:DegreeLevel',
            'Delete:DegreeLevel',
            'ViewAny:UserType',
            'Create:UserType',
            'Update:UserType',
            'Delete:UserType',
            'ViewAny:CandidateList',
            'Create:CandidateList',
            'Update:CandidateList',
            'Delete:CandidateList',
            'ViewAny:CandidateSubmitPopupSetting',
            'Update:CandidateSubmitPopupSetting',
        ];
        $registrarOfficerPermissions = [
            'ViewAny:CustomFormEntry',
            'Create:CustomFormEntry',
            'Update:CustomFormEntry',
            'Delete:CustomFormEntry',
            'View:CustomFormEntry',
            'DownloadExcel:CustomFormEntry',
            'ClearData:CustomFormEntry',
            'EditReviewNote:CustomFormEntry',
            'ViewPdf:CustomFormEntry',
            'Accepted:CustomFormEntry',
            'Rejected:CustomFormEntry',
            'DownloadPdf:CustomFormEntry',
            'ViewAny:CandidateEntranceStatistic',
            'Passed:CandidateEntranceStatistic',
            'Pending:CandidateEntranceStatistic',
            'BulkPassed:CandidateEntranceStatistic',
            'BulkPending:CandidateEntranceStatistic',
            'DownloadExcel:CandidateEntranceStatistic',
            'ClearData:CandidateEntranceStatistic',
            'ViewAny:CandidateExitStatistic',
            'Passed:CandidateExitStatistic',
            'Pending:CandidateExitStatistic',
            'BulkPassed:CandidateExitStatistic',
            'BulkPending:CandidateExitStatistic',
            'DownloadExcel:CandidateExitStatistic',
            'ClearData:CandidateExitStatistic',
            'ViewAny:ExamResult',
            'NotifyStudent:ExamResult',
            'NotifyAllStudents:ExamResult',
            'DownloadExcel:ExamResult',
            'ClearData:ExamResult',
            'ViewAny:ExitExamResult',
            'NotifyStudent:ExitExamResult',
            'NotifyAllStudents:ExitExamResult',
            'DownloadExcel:ExitExamResult',
            'ClearData:ExitExamResult',
            'ViewAny:ClosingDate',
            'Create:ClosingDate',
            'Update:ClosingDate',
            'Delete:ClosingDate',
        ];

        foreach (array_unique([
            ...$deletePermissions,
            ...$viewerPermissions,
            ...$dataEntryPermissions,
            ...$crudPermissions,
            ...$cashierManagerPermissions,
            ...$cashierOfficerPermissions,
            ...$registrarManagerPermissions,
            ...$registrarOfficerPermissions,
        ]) as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        Artisan::shouldReceive('call')->once()->andReturn(0);

        (new RolesAndPermissionsSeeder)->run();

        $roles = [
            'data_entry' => ['Data Entry', 'អ្នកបញ្ចូលទិន្នន័យ'],
            'delete' => ['Delete', 'អ្នកលុបទិន្នន័យ'],
            'viewer' => ['Viewer', 'អ្នកមើលទិន្នន័យ'],
            'crud' => ['CRUD', 'អ្នកគ្រប់គ្រងទិន្នន័យ'],
            'cashier_manager' => ['Cashier Manager', 'អ្នកគ្រប់គ្រងផ្នែកបេឡា'],
            'cashier_officer' => ['Cashier Officer', 'មន្ត្រីបេឡា'],
            'registrar_manager' => ['Registrar Manager', 'អ្នកគ្រប់គ្រងការិយាល័យចុះឈ្មោះ'],
            'registrar_officer' => ['Registrar Officer', 'មន្ត្រីការិយាល័យចុះឈ្មោះ'],
        ];

        foreach ($roles as $name => [$labelEn, $labelKh]) {
            $role = Role::query()
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->firstOrFail();

            $this->assertSame('staff', $role->role_type_key);
            $this->assertSame($labelEn, $role->label_en);
            $this->assertSame($labelKh, $role->name_kh);

            if ($name === 'delete') {
                $this->assertSame(
                    collect($deletePermissions)->sort()->values()->all(),
                    $role->permissions->pluck('name')->sort()->values()->all(),
                );
                $this->assertFalse($role->permissions->contains('name', 'Create:Payment'));
                $this->assertFalse($role->permissions->contains('name', 'Update:Payment'));
                $this->assertFalse($role->permissions->contains('name', 'Pay:UnpaidApplication'));
                $this->assertFalse($role->permissions->contains('name', 'Update:PaymentType'));
                $this->assertFalse($role->permissions->contains('name', 'Create:GeoLocation'));
                $this->assertFalse($role->permissions->contains('name', 'Update:GeoLocation'));
                $this->assertFalse($role->permissions->contains('name', 'Create:SystemUser'));
                $this->assertFalse($role->permissions->contains('name', 'Update:SystemUser'));
                $this->assertFalse($role->permissions->contains('name', 'Create:RoleType'));
                $this->assertFalse($role->permissions->contains('name', 'Update:RoleType'));
                $this->assertFalse($role->permissions->contains('name', 'Create:Role'));
                $this->assertFalse($role->permissions->contains('name', 'Update:Role'));
            } elseif ($name === 'viewer') {
                $this->assertSame(
                    collect($viewerPermissions)->sort()->values()->all(),
                    $role->permissions->pluck('name')->sort()->values()->all(),
                );
                $this->assertFalse($role->permissions->contains('name', 'Create:Payment'));
                $this->assertFalse($role->permissions->contains('name', 'Update:PaymentType'));
                $this->assertFalse($role->permissions->contains('name', 'Delete:CandidateList'));
                $this->assertFalse($role->permissions->contains('name', 'Create:SystemUser'));
                $this->assertFalse($role->permissions->contains('name', 'Update:SystemUser'));
                $this->assertFalse($role->permissions->contains('name', 'Delete:SystemUser'));
                $this->assertFalse($role->permissions->contains('name', 'ClearData:AuditLog'));
            } elseif ($name === 'data_entry') {
            } elseif ($name === 'data_entry') {
                $this->assertSame(
                    collect($dataEntryPermissions)->sort()->values()->all(),
                    $role->permissions->pluck('name')->sort()->values()->all(),
                );
                $this->assertFalse($role->permissions->contains('name', 'Delete:CustomFormEntry'));
                $this->assertFalse($role->permissions->contains('name', 'ClearData:Payment'));
                $this->assertFalse($role->permissions->contains('name', 'Delete:PaymentType'));
                $this->assertFalse($role->permissions->contains('name', 'Delete:CandidateList'));
            } elseif ($name === 'crud') {
                $this->assertSame(
                    collect($crudPermissions)->sort()->values()->all(),
                    $role->permissions->pluck('name')->sort()->values()->all(),
                );
            } elseif ($name === 'cashier_manager') {
                $this->assertSame(
                    collect($cashierManagerPermissions)->sort()->values()->all(),
                    $role->permissions->pluck('name')->sort()->values()->all(),
                );
                $this->assertFalse($role->permissions->contains('name', 'Create:Payment'));
                $this->assertFalse($role->permissions->contains('name', 'Delete:Payment'));
                $this->assertFalse($role->permissions->contains('name', 'Update:UnpaidApplication'));
            } elseif ($name === 'cashier_officer') {
                $this->assertSame(
                    collect($cashierOfficerPermissions)->sort()->values()->all(),
                    $role->permissions->pluck('name')->sort()->values()->all(),
                );
                $this->assertFalse($role->permissions->contains('name', 'ViewAny:PaymentType'));
                $this->assertFalse($role->permissions->contains('name', 'Update:ExchangeRate'));
                $this->assertFalse($role->permissions->contains('name', 'Create:Payment'));
            } elseif ($name === 'registrar_manager') {
                $this->assertSame(
                    collect($registrarManagerPermissions)->sort()->values()->all(),
                    $role->permissions->pluck('name')->sort()->values()->all(),
                );
                $this->assertFalse($role->permissions->contains('name', 'ViewAny:GeoLocation'));
                $this->assertFalse($role->permissions->contains('name', 'ViewAny:PaymentType'));
                $this->assertFalse($role->permissions->contains('name', 'ViewAny:ExchangeRate'));
            } elseif ($name === 'registrar_officer') {
                $this->assertSame(
                    collect($registrarOfficerPermissions)->sort()->values()->all(),
                    $role->permissions->pluck('name')->sort()->values()->all(),
                );
                $this->assertFalse($role->permissions->contains('name', 'ViewAny:CustomForm'));
                $this->assertFalse($role->permissions->contains('name', 'ViewAny:PaymentType'));
                $this->assertFalse($role->permissions->contains('name', 'ViewAny:CandidateList'));
            } else {
                $this->assertCount(0, $role->permissions);
            }
        }

        Artisan::shouldReceive('call')->once()->andReturn(0);

        (new RolesAndPermissionsSeeder)->run();

        $this->assertSame(
            count($roles),
            Role::query()->whereIn('name', array_keys($roles))->count(),
        );
    }
}
