<?php

namespace Tests\Feature;

use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class WorkflowDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_form_upsert_uses_the_default_result_menu_when_definition_is_null(): void
    {
        $method = new ReflectionMethod(WorkflowDemoSeeder::class, 'upsertForm');

        $method->invoke(new WorkflowDemoSeeder, [
            'slug' => 'continuing-bachelors-degree',
            'name' => ['en' => "Continuing Bachelor's Degree", 'km' => 'បន្តបរិញ្ញាបត្រ'],
            'role' => 'candidate',
            'allowed_roles' => ['student', 'candidate'],
            'passed_result_menu' => null,
        ], 1);

        $this->assertSame(0, DB::table('custom_forms')->whereNull('passed_result_menu')->count());
        $this->assertSame(
            'exam_results',
            DB::table('custom_forms')
                ->where('slug', 'continuing-bachelors-degree')
                ->value('passed_result_menu'),
        );
    }

    public function test_demo_form_upsert_preserves_exit_exam_result_menu(): void
    {
        $method = new ReflectionMethod(WorkflowDemoSeeder::class, 'upsertForm');

        $method->invoke(new WorkflowDemoSeeder, [
            'slug' => 'national-exit-exam-application-bachelor',
            'name' => ['en' => 'National Exit Exam Application-Bachelor', 'km' => 'ប្រឡងចេញ'],
            'role' => 'national_exit_exam_application_bachelor',
            'allowed_roles' => ['student', 'candidate'],
            'passed_result_menu' => 'exit_exam_results',
        ], 1);

        $this->assertSame(
            'exit_exam_results',
            DB::table('custom_forms')
                ->where('slug', 'national-exit-exam-application-bachelor')
                ->value('passed_result_menu'),
        );
    }
}
