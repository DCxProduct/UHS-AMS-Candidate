<?php

namespace Database\Seeders;

use App\Models\SystemUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class WorkflowDemoSeeder extends Seeder
{
    private const BATCH = 'workflow-demo-2026-09';

    private const MIN_ENTRIES_PER_FORM = 5;

    private const ENTRY_COUNT_VARIATIONS = 5;

    private ?string $demoPasswordHash = null;

    public function run(): void
    {
        if (! $this->requiredTablesExist()) {
            $this->command?->warn('WorkflowDemoSeeder skipped: required form or payment tables are missing.');

            return;
        }

        DB::transaction(function (): void {
            $this->seedPaymentReferences();
            $forms = $this->resolveForms();
            $this->seedReviewTemplates($forms);
            $reviewerId = $this->reviewerId();

            if (count($forms) < 1 || ! $reviewerId) {
                $this->command?->warn('WorkflowDemoSeeder skipped: at least one form and an admin reviewer are required.');

                return;
            }

            $this->removePreviousBatch();

            $number = 0;
            $totalEntries = 0;

            foreach ($forms as $formIndex => $form) {
                $entriesForForm = self::MIN_ENTRIES_PER_FORM + ($formIndex % self::ENTRY_COUNT_VARIATIONS);
                $totalEntries += $entriesForForm;

                for ($formNumber = 0; $formNumber < $entriesForForm; $formNumber++) {
                    $number++;
                    $candidate = $this->candidate($number, $form['role']);
                    $scenario = $this->scenario($formNumber);
                    $submittedAt = now()->subDays($totalEntries - $number + 1);

                    $data = $this->entryData($number, $form, $candidate, $scenario, $submittedAt);
                    $entryId = DB::table('custom_form_entries')->insertGetId([
                        'custom_form_id' => $form['id'],
                        'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                        'created_by' => $candidate->id,
                        'reviewed_by' => $scenario['reviewed'] ? $reviewerId : null,
                        'review_status' => $scenario['review_status'],
                        'review_note' => $scenario['review_note'],
                        'reviewed_at' => $scenario['reviewed'] ? $submittedAt->copy()->addDay() : null,
                        'created_at' => $submittedAt,
                        'updated_at' => $scenario['reviewed'] ? $submittedAt->copy()->addDay() : $submittedAt,
                    ]);

                    if ($scenario['payment_record']) {
                        $this->createPayment($entryId, $form['id'], $candidate->id, $number, $scenario, $submittedAt);
                    }
                }
            }
        });
    }

    private function seedReviewTemplates(array $forms): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        $now = now();
        $content = $this->reviewTemplateContent();

        foreach ($forms as $form) {
            $type = 'custom_form_' . $form['id'];
            $existing = DB::table('document_templates')->where('type', $type)->first();

            $data = [
                'name' => json_encode([
                    'en' => 'Application Review',
                    'km' => 'ពិនិត្យពាក្យស្នើសុំ',
                    'kh' => 'ពិនិត្យពាក្យស្នើសុំ',
                ], JSON_UNESCAPED_UNICODE),
                'type' => $type,
                'custom_form_id' => $form['id'],
                'model_class' => \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::class,
                'content' => $content,
                'page_settings' => json_encode([
                    'format' => 'a4',
                    'orientation' => 'portrait',
                    'margin_left' => 15,
                    'margin_right' => 15,
                    'margin_top' => 15,
                    'margin_bottom' => 15,
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ];

            if ($existing) {
                $updates = [
                    'custom_form_id' => $form['id'],
                    'model_class' => $data['model_class'],
                    'updated_at' => $now,
                ];

                if (blank($existing->content)) {
                    $updates['content'] = $content;
                }

                DB::table('document_templates')->where('id', $existing->id)->update($updates);
            } else {
                DB::table('document_templates')->insert([...$data, 'created_at' => $now]);
            }
        }
    }

    private function reviewTemplateContent(): string
    {
        return <<<'HTML'
<div style="font-family: DejaVu Sans, sans-serif; font-size: 14px; line-height: 1.8; padding: 24px;">
    <h1 style="text-align: center;">ពាក្យស្នើសុំ</h1>
    <h2 style="text-align: center;">Application Review</h2>
    <hr>
    <p><strong>លេខសម្គាល់និស្សិត៖</strong> {{ student_id }}</p>
    <p><strong>នាមត្រកូល៖</strong> {{ last_name_kh }} &nbsp;&nbsp; <strong>នាមខ្លួន៖</strong> {{ first_name_kh }}</p>
    <p><strong>Latin Name:</strong> {{ first_name_en }} {{ last_name_en }}</p>
    <p><strong>ភេទ៖</strong> {{ gender }} &nbsp;&nbsp; <strong>ថ្ងៃខែឆ្នាំកំណើត៖</strong> {{ date_of_birth }}</p>
    <p><strong>លេខទូរស័ព្ទ៖</strong> {{ phone_number }}</p>
    <p><strong>អ៊ីមែល៖</strong> {{ email }}</p>
    <p><strong>ឆ្នាំសិក្សា៖</strong> {{ academic_year }}</p>
    <p><strong>ជំនាញ៖</strong> {{ major }}</p>
    <p><strong>ស្ថានភាពទូទាត់៖</strong> {{ payment_status }}</p>
    <p><strong>ស្ថានភាពពាក្យស្នើសុំ៖</strong> {{ registration_status }}</p>
</div>
HTML;
    }

    private function requiredTablesExist(): bool
    {
        return Schema::hasTable('custom_forms')
            && Schema::hasTable('custom_form_fields')
            && Schema::hasTable('custom_form_entries')
            && Schema::hasTable('payments')
            && Schema::hasTable('payment_types')
            && Schema::hasTable('exchange_rates');
    }

    private function seedPaymentReferences(): void
    {
        $now = now();

        $paymentTypes = [
            ['key' => 'cash', 'name_en' => 'Cash', 'name_kh' => 'សាច់ប្រាក់', 'display_order' => 1],
            ['key' => 'ftb', 'name_en' => 'FTB', 'name_kh' => 'FTB', 'display_order' => 2],
            ['key' => 'acleda', 'name_en' => 'Acleda', 'name_kh' => 'Acleda', 'display_order' => 3],
        ];

        DB::table('payment_types')
            ->whereNotIn('key', array_column($paymentTypes, 'key'))
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        foreach ($paymentTypes as $paymentType) {
            $existing = DB::table('payment_types')->where('key', $paymentType['key'])->first();
            $data = [...$paymentType, 'is_active' => true, 'updated_at' => $now];

            if ($existing) {
                DB::table('payment_types')->where('id', $existing->id)->update($data);
            } else {
                DB::table('payment_types')->insert([...$data, 'created_at' => $now]);
            }
        }

        $rate = DB::table('exchange_rates')
            ->where('base_currency', 'USD')
            ->where('quote_currency', 'KHR')
            ->first();

        if ($rate) {
            DB::table('exchange_rates')->where('id', $rate->id)->update([
                'rate' => 4100,
                'is_active' => true,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('exchange_rates')->insert([
                'base_currency' => 'USD',
                'quote_currency' => 'KHR',
                'rate' => 4100,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Seed every active custom form so the demo data follows the current form menu.
     */
    private function resolveForms(): array
    {
        $existingForms = DB::table('custom_forms')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('slug', '!=', 'profile')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->values();

        if ($existingForms->isEmpty()) {
            $fallbackDefinitions = [
                [
                    'slug' => 'national-entrance-exam-application',
                    'name' => ['en' => 'National Entrance Exam Application', 'km' => 'ពាក្យសុំប្រឡងចូលថ្នាក់ជាតិ', 'kh' => 'ពាក្យសុំប្រឡងចូលថ្នាក់ជាតិ'],
                    'role' => 'national_entrance_exam_application_bachelor',
                    'passed_result_menu' => 'exam_results',
                ],
                [
                    'slug' => 'national-exit-exam-application',
                    'name' => ['en' => 'National Exit Exam Application', 'km' => 'ពាក្យសុំប្រឡងចេញថ្នាក់ជាតិ', 'kh' => 'ពាក្យសុំប្រឡងចេញថ្នាក់ជាតិ'],
                    'role' => 'national_exit_exam_application_bachelor',
                    'passed_result_menu' => 'exit_exam_results',
                ],
            ];

            foreach ($fallbackDefinitions as $index => $definition) {
                $formId = $this->upsertForm($definition, $index + 1);
                $existingForms->push((object) [
                    'id' => $formId,
                    'slug' => $definition['slug'],
                    'name' => json_encode($definition['name'], JSON_UNESCAPED_UNICODE),
                    'allowed_roles' => json_encode([$definition['role']], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        return $existingForms->values()->map(function (object $form): array {
            $allowedRoles = json_decode((string) ($form->allowed_roles ?? ''), true);
            $role = is_array($allowedRoles) && filled($allowedRoles[0] ?? null)
                ? (string) $allowedRoles[0]
                : 'candidate';

            if (Schema::hasColumn('custom_forms', 'passed_result_menu')) {
                $passedResultMenu = str_contains((string) $form->slug, 'national-entrance-exam-application')
                    ? 'exam_results'
                    : (str_contains((string) $form->slug, 'national-exit-exam-application') ? 'exit_exam_results' : null);

                if ($passedResultMenu) {
                    DB::table('custom_forms')->where('id', $form->id)->update([
                        'passed_result_menu' => $passedResultMenu,
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->ensureFields((int) $form->id);

            return [
                'id' => (int) $form->id,
                'slug' => (string) $form->slug,
                'name' => $form->name,
                'role' => $role,
            ];
        })->all();
    }

    private function upsertForm(array $definition, int $displayOrder): int
    {
        $now = now();
        $existing = DB::table('custom_forms')->where('slug', $definition['slug'])->first();
        $data = [
            'name' => json_encode($definition['name'], JSON_UNESCAPED_UNICODE),
            'slug' => $definition['slug'],
            'schema' => null,
            'is_active' => true,
            'allowed_roles' => json_encode([$definition['role']], JSON_UNESCAPED_UNICODE),
            'menu_placement' => 'sidebar',
            'parent_sidebar' => null,
            'sub_item_type' => null,
            'requires_payment' => true,
            'display_order' => $displayOrder,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('custom_forms', 'passed_result_menu')) {
            $data['passed_result_menu'] = $definition['passed_result_menu'];
        }

        if ($existing) {
            DB::table('custom_forms')->where('id', $existing->id)->update($data);

            $formId = (int) $existing->id;
        } else {
            $formId = (int) DB::table('custom_forms')->insertGetId([...$data, 'created_at' => $now]);
        }

        DB::table('custom_forms')->where('id', $formId)->update([
            'custom_form_id' => $formId,
            'updated_at' => $now,
        ]);

        return $formId;
    }

    private function ensureFields(int $formId): void
    {
        $sectionName = 'workflow_application_information';
        $section = DB::table('custom_form_fields')
            ->where('custom_form_id', $formId)
            ->where('name', $sectionName)
            ->first();

        if (! $section) {
            $sectionId = (int) DB::table('custom_form_fields')->insertGetId([
                'custom_form_id' => $formId,
                'parent_id' => null,
                'name' => $sectionName,
                'label' => json_encode(['en' => 'Application Information', 'km' => 'ព័ត៌មានពាក្យស្នើសុំ', 'kh' => 'ព័ត៌មានពាក្យស្នើសុំ'], JSON_UNESCAPED_UNICODE),
                'type' => 'section',
                'required' => false,
                'options' => json_encode(['columns' => 2, 'column_span_full' => true]),
                'sort' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $sectionId = (int) $section->id;
        }

        $fields = [
            ['student_id', 'Student ID', 'text_input', true],
            ['first_name_kh', 'First Name', 'text_input', true],
            ['last_name_kh', 'Last Name', 'text_input', true],
            ['first_name_en', 'Latin First Name', 'text_input', true],
            ['last_name_en', 'Latin Last Name', 'text_input', true],
            ['gender', 'Gender', 'select_dropdown', true],
            ['date_of_birth', 'Date of Birth', 'date_picker', true],
            ['phone_number', 'Phone Number', 'text_input', true],
            ['email', 'Email', 'text_input', true],
            ['academic_year', 'Academic Year', 'text_input', true],
            ['major', 'Major', 'select_dropdown', true],
        ];

        foreach ($fields as $sort => [$name, $label, $type, $required]) {
            $existingField = DB::table('custom_form_fields')
                ->where('custom_form_id', $formId)
                ->where('name', $name)
                ->first();

            $labelTranslations = $name === 'major'
                ? ['en' => 'Major', 'km' => 'ជំនាញ', 'kh' => 'ជំនាញ']
                : ['en' => $label, 'km' => $label, 'kh' => $label];

            $options = match ($name) {
                'gender' => ['choices' => ['male' => 'Male', 'female' => 'Female']],
                'major' => ['choices' => [
                    [
                        'value' => 'Medicine',
                        'label' => [
                            'en' => 'Medicine',
                            'km' => 'វេជ្ជសាស្ត្រ',
                            'kh' => 'វេជ្ជសាស្ត្រ',
                        ],
                    ],
                    [
                        'value' => 'Bachelor of Nursing',
                        'label' => [
                            'en' => 'Bachelor of Nursing',
                            'km' => 'បរិញ្ញាបត្រគិលានុបដ្ឋាក',
                            'kh' => 'បរិញ្ញាបត្រគិលានុបដ្ឋាក',
                        ],
                    ],
                ]],
                default => [],
            };

            if ($existingField) {
                if ($name === 'major') {
                    DB::table('custom_form_fields')->where('id', $existingField->id)->update([
                        'label' => json_encode($labelTranslations, JSON_UNESCAPED_UNICODE),
                        'type' => $type,
                        'required' => $required,
                        'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                }

                continue;
            }

            DB::table('custom_form_fields')->insert([
                'custom_form_id' => $formId,
                'parent_id' => $sectionId,
                'name' => $name,
                'label' => json_encode($labelTranslations, JSON_UNESCAPED_UNICODE),
                'type' => $type,
                'required' => $required,
                'options' => $options === [] ? null : json_encode($options, JSON_UNESCAPED_UNICODE),
                'sort' => $sort + 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function reviewerId(): ?int
    {
        return DB::table('users')
            ->whereIn('username', ['admin', 'registrar'])
            ->orderByRaw("CASE username WHEN 'admin' THEN 1 ELSE 2 END")
            ->value('id');
    }

    private function candidateName(int $number): array
    {
        $firstNames = [
            ['kh' => 'សុភា', 'en' => 'Sophea'],
            ['kh' => 'សុខា', 'en' => 'Sokha'],
            ['kh' => 'វិសាល', 'en' => 'Visal'],
            ['kh' => 'ដារ៉ា', 'en' => 'Dara'],
            ['kh' => 'រតនា', 'en' => 'Rattana'],
            ['kh' => 'ចន្ទ្រា', 'en' => 'Chantra'],
            ['kh' => 'មុនី', 'en' => 'Mony'],
            ['kh' => 'ស្រីពៅ', 'en' => 'Sreypov'],
            ['kh' => 'កក្កដា', 'en' => 'Kakada'],
            ['kh' => 'បញ្ញា', 'en' => 'Panha'],
            ['kh' => 'វុទ្ធី', 'en' => 'Vuthy'],
            ['kh' => 'សុវណ្ណ', 'en' => 'Sovann'],
            ['kh' => 'លីណា', 'en' => 'Lina'],
            ['kh' => 'មាលី', 'en' => 'Maly'],
            ['kh' => 'ពិសី', 'en' => 'Pisey'],
            ['kh' => 'ធារី', 'en' => 'Thary'],
            ['kh' => 'សុជាតិ', 'en' => 'Socheat'],
            ['kh' => 'នារី', 'en' => 'Nary'],
            ['kh' => 'កុសល', 'en' => 'Kosal'],
            ['kh' => 'បូរី', 'en' => 'Borey'],
            ['kh' => 'រ៉ាវី', 'en' => 'Ravy'],
            ['kh' => 'ដាលីន', 'en' => 'Dalin'],
            ['kh' => 'ស្រីនាង', 'en' => 'Sreynang'],
            ['kh' => 'អរុណ', 'en' => 'Arun'],
            ['kh' => 'វណ្ណៈ', 'en' => 'Vanna'],
            ['kh' => 'សំណាង', 'en' => 'Samnang'],
            ['kh' => 'សុម៉ាលី', 'en' => 'Somaly'],
            ['kh' => 'កញ្ញា', 'en' => 'Kanya'],
            ['kh' => 'គន្ធា', 'en' => 'Kunthea'],
            ['kh' => 'និមល', 'en' => 'Nimol'],
            ['kh' => 'ចរិយា', 'en' => 'Chariya'],
            ['kh' => 'វិរៈ', 'en' => 'Vireak'],
            ['kh' => 'សុភ័ក្រ', 'en' => 'Sophak'],
            ['kh' => 'សុវណ្ណារ៉ា', 'en' => 'Sovannara'],
            ['kh' => 'មករា', 'en' => 'Makara'],
            ['kh' => 'ពេជ្រា', 'en' => 'Pich'],
            ['kh' => 'គីមហ៊ាង', 'en' => 'Kimheang'],
            ['kh' => 'រតនៈ', 'en' => 'Ratanak'],
            ['kh' => 'ស្រីមុំ', 'en' => 'Sreymom'],
            ['kh' => 'ទេពធីតា', 'en' => 'Tepthida'],
            ['kh' => 'សុភី', 'en' => 'Sopheak'],
            ['kh' => 'វាសនា', 'en' => 'Veasna'],
            ['kh' => 'យ៉ាណា', 'en' => 'Yana'],
            ['kh' => 'ចាន់ថា', 'en' => 'Chantha'],
            ['kh' => 'នីតា', 'en' => 'Nita'],
            ['kh' => 'សុគន្ធា', 'en' => 'Sokuntha'],
            ['kh' => 'ស្រីល័ក្ខ', 'en' => 'Sreyleak'],
            ['kh' => 'ភក្តី', 'en' => 'Pheakdey'],
            ['kh' => 'ប៊ុនថន', 'en' => 'Bunthorn'],
            ['kh' => 'ឆវី', 'en' => 'Chavy'],
            ['kh' => 'ទីណា', 'en' => 'Tina'],
            ['kh' => 'អេឡែន', 'en' => 'Elen'],
            ['kh' => 'ម៉ាលីសា', 'en' => 'Malisa'],
            ['kh' => 'ហ៊ុយសេង', 'en' => 'Huyseng'],
            ['kh' => 'មុន្នីរ័ត្ន', 'en' => 'Monyroth'],
            ['kh' => 'ចំរើន', 'en' => 'Chamroeun'],
            ['kh' => 'រ៉ូហ្សា', 'en' => 'Rojah'],
            ['kh' => 'សុផានី', 'en' => 'Sophany'],
            ['kh' => 'រិទ្ធី', 'en' => 'Rithy'],
            ['kh' => 'លក្ខិណា', 'en' => 'Leakena'],
            ['kh' => 'ទេវី', 'en' => 'Theavy'],
            ['kh' => 'ម៉េងហួត', 'en' => 'Menghuot'],
            ['kh' => 'ធីតា', 'en' => 'Thida'],
            ['kh' => 'អានន្ទ', 'en' => 'Anand'],
            ['kh' => 'សារិន', 'en' => 'Sarin'],
            ['kh' => 'វិជិត', 'en' => 'Vicheth'],
            ['kh' => 'មនោរម្យ', 'en' => 'Monorom'],
            ['kh' => 'លីហួរ', 'en' => 'Lyhour'],
            ['kh' => 'ដេវីត', 'en' => 'Davit'],
            ['kh' => 'សុវត្ថិ', 'en' => 'Savuth'],
            ['kh' => 'អមរា', 'en' => 'Amara'],
            ['kh' => 'សុរិយា', 'en' => 'Soriya'],
            ['kh' => 'រ៉ូសា', 'en' => 'Rosa'],
            ['kh' => 'មេតា', 'en' => 'Meta'],
            ['kh' => 'សុវណ្ណី', 'en' => 'Sovanny'],
            ['kh' => 'វីរ៉ា', 'en' => 'Vira'],
            ['kh' => 'សុជាតា', 'en' => 'Socheata'],
            ['kh' => 'ឌីណា', 'en' => 'Dina'],
            ['kh' => 'ពិសាខ', 'en' => 'Pisakh'],
            ['kh' => 'កែវមុនី', 'en' => 'Keovmony'],
            ['kh' => 'ស្រីអូន', 'en' => 'Sreyoun'],
            ['kh' => 'រ៉េតនា', 'en' => 'Retana'],
            ['kh' => 'សុវណ្ណរិទ្ធ', 'en' => 'Sovannrith'],
            ['kh' => 'ច័ន្ទគ្រឹស្នា', 'en' => 'Chandkrishna'],
            ['kh' => 'រដ្ឋា', 'en' => 'Ratha'],
            ['kh' => 'សិរីមង្គល', 'en' => 'Sereymongkol'],
            ['kh' => 'នាថា', 'en' => 'Neatha'],
            ['kh' => 'បូរីដា', 'en' => 'Boreyda'],
        ];
        $lastNamesKh = ['កែវ', 'សុខ', 'ចាន់', 'ហេង', 'លី', 'វ៉ាន់', 'នួន'];
        $lastNamesEn = ['Keo', 'Sok', 'Chan', 'Heng', 'Ly', 'Van', 'Nuon'];

        $zeroBasedNumber = max(0, $number - 1);
        $lastNameIndex = $zeroBasedNumber % count($lastNamesKh);
        $firstNameIndex = $zeroBasedNumber % count($firstNames);

        return [
            'first_name_kh' => $firstNames[$firstNameIndex]['kh'],
            'last_name_kh' => $lastNamesKh[$lastNameIndex],
            'first_name_en' => $firstNames[$firstNameIndex]['en'],
            'last_name_en' => $lastNamesEn[$lastNameIndex],
        ];
    }

    private function candidate(int $number, string $role): User
    {
        $suffix = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        $legacyUsername = 'workflow_candidate_' . $suffix;
        $password = $this->demoPasswordHash ??= Hash::make('1234567a');
        $names = $this->candidateName($number);
        $legacyNameUsername = strtolower($names['first_name_en'] . '.' . $names['last_name_en']);
        $username = strtolower($names['first_name_en'] . $names['last_name_en']);
        $email = $username . '@gmail.com';
        $user = User::query()
            ->whereIn('username', [$username, $legacyNameUsername, $legacyUsername])
            ->first() ?? new User();

        $user->fill([
            'registration_type' => 'student',
            'academic_year' => '2025-2026',
            'name' => $names['first_name_kh'] . ' ' . $names['last_name_kh'],
            'name_latin' => $names['first_name_en'] . ' ' . $names['last_name_en'],
            'username' => $username,
            'email' => $email,
            'email_verified_at' => now(),
            'phone' => '012' . str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'date_of_birth' => now()->subYears(18 + ($number % 8))->subDays($number)->toDateString(),
            'seat_number' => 'DEMO-' . $suffix,
            'is_active' => true,
            'password' => $password,
        ]);
        $user->save();

        $user->syncRoles(['candidate']);

        $systemUser = SystemUser::query()
            ->whereIn('username', [$username, $legacyNameUsername, $legacyUsername])
            ->first() ?? new SystemUser();
        $systemUser->fill([
            'username' => $username,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => $password,
            'roles' => [$role],
            'permissions' => null,
            'is_active' => true,
            'email_verified_at' => now(),
            'last_login_at' => null,
        ]);
        $systemUser->save();

        return $user->fresh();
    }

    private function scenario(int $formNumber): array
    {
        return match ($formNumber % 5) {
            0 => [
                'review_status' => 'accepted',
                'reviewed' => true,
                'review_note' => 'Accepted and waiting for cashier payment.',
                'payment_record' => false,
                'payment_status' => 'unpaid',
                'payment_channel' => 'in_system',
            ],
            1 => [
                'review_status' => 'accepted',
                'reviewed' => true,
                'review_note' => 'Accepted and paid through the system.',
                'passed' => true,
                'payment_record' => true,
                'payment_status' => 'paid',
                'payment_channel' => 'in_system',
            ],
            2 => [
                'review_status' => 'accepted',
                'reviewed' => true,
                'review_note' => 'Checked and external payment completed.',
                'passed' => true,
                'payment_record' => true,
                'payment_status' => 'paid',
                'payment_channel' => 'external',
            ],
            3 => [
                'review_status' => 'rejected',
                'reviewed' => true,
                'review_note' => 'Please complete the missing application information before resubmitting.',
                'payment_record' => false,
                'payment_status' => 'unpaid',
                'payment_channel' => 'in_system',
            ],
            default => [
                'review_status' => 'pending',
                'reviewed' => false,
                'review_note' => null,
                'payment_record' => false,
                'payment_status' => 'pending',
                'payment_channel' => 'in_system',
            ],
        };
    }

    private function entryData(int $number, array $form, User $candidate, array $scenario, $submittedAt): array
    {
        $suffix = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        $names = $this->candidateName($number);
        $passed = (bool) ($scenario['passed'] ?? false);
        $data = [
            'student_id' => 'WF-' . $suffix,
            'first_name_kh' => $names['first_name_kh'],
            'last_name_kh' => $names['last_name_kh'],
            'first_name_en' => $names['first_name_en'],
            'last_name_en' => $names['last_name_en'],
            'gender' => $number % 2 === 0 ? 'female' : 'male',
            'date_of_birth' => $candidate->date_of_birth?->toDateString(),
            'phone_number' => $candidate->phone,
            'email' => $candidate->email,
            'academic_year' => '2025-2026',
            'major' => $number % 2 === 0 ? 'Medicine' : 'Bachelor of Nursing',
            'form_slug' => $form['slug'],
            'registration_status' => $scenario['review_status'] === 'rejected' ? 'rejected' : $scenario['review_status'],
            'candidate_status' => $passed ? 'passed' : 'pending',
            'submitted_at' => $submittedAt->toDateTimeString(),
            'payment_status' => $scenario['payment_status'],
            'payment_channel' => $scenario['payment_channel'],
            'seed_batch' => self::BATCH,
        ];

        if ($scenario['payment_channel'] === 'external') {
            $data['external_payment_reference'] = 'EXT-' . $suffix;
            $data['external_payment_provider'] = $number % 2 === 0 ? 'ABA' : 'Cash Office';
            $data['external_payment_date'] = $submittedAt->copy()->addDay()->toDateString();
        }

        if ($passed) {
            $data['candidate_reviewed_at'] = $submittedAt->copy()->addDays(2)->toDateTimeString();
        }

        return $data;
    }

    private function createPayment(int $entryId, int $formId, int $userId, int $number, array $scenario, $submittedAt): void
    {
        $usd = $number % 2 === 0 ? 10.00 : 5.00;

        DB::table('payments')->insert([
            'users_id' => $userId,
            'form_id' => $formId,
            'custom_form_entry_id' => $entryId,
            'receipt_number' => 'WF-PAY-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'type_payment' => ['cash', 'ftb', 'acleda'][$number % 3],
            'exchange_rate' => 4100,
            'status_payt' => $scenario['payment_status'],
            'amount_usd' => $usd,
            'amount_kh' => $usd * 4100,
            'datetime_pay' => $submittedAt->copy()->addDay(),
            'status' => true,
            'description' => '[workflow-demo] ' . ucfirst($scenario['payment_status']) . ' payment for seeded application.',
            'payment_slip_path' => 'payment-slips/01M1X1Z918WMP7FJ9Z9R1069D4.png',
            'created_at' => $submittedAt->copy()->addDay(),
            'updated_at' => $submittedAt->copy()->addDay(),
        ]);
    }

    private function removePreviousBatch(): void
    {
        $entryIds = DB::table('custom_form_entries')
            ->get(['id', 'data'])
            ->filter(function (object $entry): bool {
                $data = is_array($entry->data) ? $entry->data : json_decode((string) $entry->data, true);

                return is_array($data) && ($data['seed_batch'] ?? null) === self::BATCH;
            })
            ->pluck('id')
            ->all();

        if ($entryIds !== []) {
            DB::table('payments')->whereIn('custom_form_entry_id', $entryIds)->delete();
            DB::table('custom_form_entries')->whereIn('id', $entryIds)->delete();
        }

        DB::table('payments')
            ->where('description', 'like', '[workflow-demo]%')
            ->delete();
    }
}
