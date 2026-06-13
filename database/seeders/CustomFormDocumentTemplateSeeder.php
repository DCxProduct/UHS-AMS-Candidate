<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomFormDocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('custom_forms')) {
            $this->command?->error('Table custom_forms does not exist.');
            return;
        }

        if (! Schema::hasTable('document_templates')) {
            $this->command?->error('Table document_templates does not exist.');
            return;
        }

        $forms = DB::table('custom_forms')
            ->whereIn('slug', [
                'profile',
                'enrollment',
            ])
            ->get();

        foreach ($forms as $form) {
            $type = 'custom_form_' . $form->id;

            DB::table('document_templates')->updateOrInsert(
                [
                    'type' => $type,
                ],
                [
                    'name' => $form->name . ' Template',
                    'model_class' => 'Chanthoeun\\FilamentCustomForms\\Models\\CustomFormEntry',
                    'content' => $this->templateContent($form->slug, $form->name),
                    'page_settings' => json_encode([
                        'paper_size' => 'a4',
                        'orientation' => 'portrait',
                        'margin_top' => 15,
                        'margin_right' => 15,
                        'margin_bottom' => 15,
                        'margin_left' => 15,
                    ], JSON_UNESCAPED_UNICODE),
                    'extra_data_sources' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function templateContent(string $slug, string $name): string
    {
        if ($slug === 'profile') {
            return <<<'HTML'
<div style="font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; color: #111;">
    <h2 style="text-align:center;">Profile</h2>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="6">
        <tr>
            <td><strong>First Name KH</strong></td>
            <td>{{ data.first_name_kh }}</td>
            <td><strong>Last Name KH</strong></td>
            <td>{{ data.last_name_kh }}</td>
        </tr>
        <tr>
            <td><strong>First Name EN</strong></td>
            <td>{{ data.first_name_en }}</td>
            <td><strong>Last Name EN</strong></td>
            <td>{{ data.last_name_en }}</td>
        </tr>
        <tr>
            <td><strong>Date of Birth</strong></td>
            <td>{{ data.date_of_birth }}</td>
            <td><strong>Gender</strong></td>
            <td>{{ data.gender }}</td>
        </tr>
        <tr>
            <td><strong>Nationality</strong></td>
            <td>{{ data.nationality }}</td>
            <td><strong>Phone</strong></td>
            <td>{{ data.phone_number }}</td>
        </tr>
    </table>
</div>
HTML;
        }

        if ($slug === 'enrollment') {
            return <<<'HTML'
<div style="font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; color: #111;">
    <h2 style="text-align:center;">Enrollment</h2>

    <table style="width:100%; border-collapse:collapse;" border="1" cellpadding="6">
        <tr>
            <td><strong>Student ID</strong></td>
            <td>{{ data.student_id }}</td>
            <td><strong>Student Status</strong></td>
            <td>{{ data.student_status }}</td>
        </tr>
        <tr>
            <td><strong>First Name KH</strong></td>
            <td>{{ data.first_name_kh }}</td>
            <td><strong>Last Name KH</strong></td>
            <td>{{ data.last_name_kh }}</td>
        </tr>
        <tr>
            <td><strong>First Name EN</strong></td>
            <td>{{ data.first_name_en }}</td>
            <td><strong>Last Name EN</strong></td>
            <td>{{ data.last_name_en }}</td>
        </tr>
        <tr>
            <td><strong>Academic Year</strong></td>
            <td>{{ data.academic_year }}</td>
            <td><strong>Department</strong></td>
            <td>{{ data.department }}</td>
        </tr>
        <tr>
            <td><strong>Phone</strong></td>
            <td>{{ data.phone_number }}</td>
            <td><strong>Email</strong></td>
            <td>{{ data.email }}</td>
        </tr>
    </table>
</div>
HTML;
        }

        return '<h2>' . e($name) . '</h2>';
    }
}
