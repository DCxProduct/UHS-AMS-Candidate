<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Generate permissions using Filament Shield automatically
        Artisan::call('shield:generate', [
            '--all' => true,
            '--ignore-existing-policies' => true,
            '--panel' => 'app',
            '--no-interaction' => true,
        ]);

        $systemAdminRoles = collect([
            'admin' => [
                'label_en' => 'Admin',
                'name_kh' => 'អ្នកគ្រប់គ្រង',
            ],
            'data_entry' => [
                'label_en' => 'Data Entry',
                'name_kh' => 'អ្នកបញ្ចូលទិន្នន័យ',
            ],
            'delete' => [
                'label_en' => 'Delete',
                'name_kh' => 'អ្នកលុបទិន្នន័យ',
            ],
            'viewer' => [
                'label_en' => 'Viewer',
                'name_kh' => 'អ្នកមើលទិន្នន័យ',
            ],
            'crud' => [
                'label_en' => 'CRUD',
                'name_kh' => 'អ្នកគ្រប់គ្រងទិន្នន័យ',
            ],
            'cashier_manager' => [
                'label_en' => 'Cashier Manager',
                'name_kh' => 'អ្នកគ្រប់គ្រងផ្នែកបេឡា',
            ],
            'cashier_officer' => [
                'label_en' => 'Cashier Officer',
                'name_kh' => 'មន្ត្រីបេឡា',
            ],
            'registrar_manager' => [
                'label_en' => 'Registrar Manager',
                'name_kh' => 'អ្នកគ្រប់គ្រងការិយាល័យចុះឈ្មោះ',
            ],
            'registrar_officer' => [
                'label_en' => 'Registrar Officer',
                'name_kh' => 'មន្ត្រីការិយាល័យចុះឈ្មោះ',
            ],
        ])->mapWithKeys(fn (array $attributes, string $role): array => [
            $role => Role::query()->updateOrCreate(
                [
                    'name' => $role,
                    'guard_name' => 'web',
                ],
                [
                    'label_en' => $attributes['label_en'],
                    'name_kh' => $attributes['name_kh'],
                    'role_type_key' => 'staff',
                ]
            ),
        ]);

        $userRoles = collect([
            'candidate' => [
                'label_en' => 'Candidate',
                'name_kh' => 'បេក្ខជន',
            ],
        ])->mapWithKeys(fn (array $attributes, string $role): array => [
            $role => Role::query()->updateOrCreate(
                [
                    'name' => $role,
                    'guard_name' => 'web',
                ],
                [
                    'label_en' => $attributes['label_en'],
                    'name_kh' => $attributes['name_kh'],
                    'role_type_key' => 'candidate',
                ]
            ),
        ]);

        $admin = $systemAdminRoles['admin'];
        $dataEntry = $systemAdminRoles['data_entry'];
        $crud = $systemAdminRoles['crud'];
        $deleteRole = $systemAdminRoles['delete'];
        $viewer = $systemAdminRoles['viewer'];
        $cashierManager = $systemAdminRoles['cashier_manager'];
        $cashierOfficer = $systemAdminRoles['cashier_officer'];
        $registrarManager = $systemAdminRoles['registrar_manager'];
        $registrarOfficer = $systemAdminRoles['registrar_officer'];
        $candidate = $userRoles['candidate'];

        $adminExcludedPermissions = [
            'Create:CustomFormEntry',
        ];

        $manageablePermissionNames = ShieldRoleResource::manageablePermissionNames();

        $admin->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $manageablePermissionNames)
                ->whereNotIn('name', $adminExcludedPermissions)
                ->get()
        );
        $deleteRole->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
                    'ViewAny:CustomFormEntry',
                    'Delete:CustomFormEntry',
                    'ViewAny:UnpaidApplication',
                    'ClearData:UnpaidApplication',
                    'ViewAny:Payment',
                    'ClearData:Payment',
                    'ViewAny:CandidateRequested',
                    'ClearData:CandidateRequested',
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
                    'ViewAny:PaymentType',
                    'Delete:PaymentType',
                    'ViewAny:ExchangeRate',
                    'ViewAny:CandidateSubmitPopupSetting',
                ])
                ->get()
        );
        $viewer->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
                    'ViewAny:CustomFormEntry',
                    'ViewAny:UnpaidApplication',
                    'ViewAny:Payment',
                    'ViewAny:CandidateRequested',
                    'ViewAny:ExamResult',
                    'ViewAny:ExitExamResult',
                    'ViewAny:CustomForm',
                    'ViewAny:DocumentTemplate',
                    'ViewAny:ClosingDate',
                    'ViewAny:DegreeLevel',
                    'ViewAny:UserType',
                    'ViewAny:CandidateList',
                    'ViewAny:PaymentType',
                    'ViewAny:ExchangeRate',
                    'ViewAny:CandidateSubmitPopupSetting',
                ])
                ->get()
        );
        $dataEntry->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
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
                    'ViewAny:CandidateRequested',
                    'Passed:CandidateRequested',
                    'Pending:CandidateRequested',
                    'BulkPassed:CandidateRequested',
                    'BulkPending:CandidateRequested',
                    'DownloadExcel:CandidateRequested',
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
                    'ViewAny:PaymentType',
                    'Create:PaymentType',
                    'Update:PaymentType',
                    'ViewAny:ExchangeRate',
                    'Update:ExchangeRate',
                    'ViewAny:CandidateSubmitPopupSetting',
                    'Update:CandidateSubmitPopupSetting',
                ])
                ->get()
        );
        $crud->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
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
                    'ViewAny:CandidateRequested',
                    'Passed:CandidateRequested',
                    'Pending:CandidateRequested',
                    'BulkPassed:CandidateRequested',
                    'BulkPending:CandidateRequested',
                    'DownloadExcel:CandidateRequested',
                    'ClearData:CandidateRequested',
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
                    'ViewAny:PaymentType',
                    'Create:PaymentType',
                    'Update:PaymentType',
                    'Delete:PaymentType',
                    'ViewAny:ExchangeRate',
                    'Update:ExchangeRate',
                    'ViewAny:CandidateSubmitPopupSetting',
                    'Update:CandidateSubmitPopupSetting',
                ])
                ->get()
        );
        $cashierManager->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
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
                ])
                ->get()
        );
        $cashierOfficer->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
                    'ViewAny:UnpaidApplication',
                    'Pay:UnpaidApplication',
                    'DownloadExcel:UnpaidApplication',
                    'ClearData:UnpaidApplication',
                    'ViewAny:Payment',
                    'Update:Payment',
                    'ViewSlip:Payment',
                    'DownloadExcel:Payment',
                    'ClearData:Payment',
                ])
                ->get()
        );
        $registrarManager->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
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
                    'ViewAny:CandidateRequested',
                    'Passed:CandidateRequested',
                    'Pending:CandidateRequested',
                    'BulkPassed:CandidateRequested',
                    'BulkPending:CandidateRequested',
                    'DownloadExcel:CandidateRequested',
                    'ClearData:CandidateRequested',
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
                ])
                ->get()
        );
        $registrarOfficer->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
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
                    'ViewAny:CandidateRequested',
                    'Passed:CandidateRequested',
                    'Pending:CandidateRequested',
                    'BulkPassed:CandidateRequested',
                    'BulkPending:CandidateRequested',
                    'DownloadExcel:CandidateRequested',
                    'ClearData:CandidateRequested',
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
                ])
                ->get()
        );
        $candidate->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', [
                    'ViewAny:CustomFormEntry',
                    'View:CustomFormEntry',
                    'Create:CustomFormEntry',
                    'Update:CustomFormEntry',
                    'EditReviewNote:CustomFormEntry',
                    'DownloadPdf:CustomFormEntry',
                ])
                ->get()
        );

        $adminUser = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'username' => 'admin',
                'name' => 'Admin',
                'password' => Hash::make('12345678'),
                'registration_type' => 'admin',
                'date_of_birth' => '2000-01-01',
            ]
        );

        $adminUser->syncRoles(['admin']);

        $studentUser = User::updateOrCreate(
            ['email' => 'student@gmail.com'],
            [
                'username' => 'student',
                'name' => 'Candidate',
                'name_latin' => 'CANDIDATE',
                'password' => Hash::make('12345678'),
                'registration_type' => 'student',
                'academic_year' => '2025-2026',
                'phone' => '010000099',
                'date_of_birth' => '2001-01-01',
                'seat_number' => 'STU-0099',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $studentUser->syncRoles(['candidate']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
