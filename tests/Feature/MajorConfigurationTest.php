<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Majors\MajorResource;
use App\Models\ClosingDate;
use App\Models\DegreeLevel;
use App\Models\Major;
use App\Support\StudentDynamicFormSchema;
use App\Support\FormEntryData;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_majors_can_be_associated_with_a_closing_date(): void
    {
        $major = Major::query()->create([
            'key' => 'Medicine Major',
            'abbreviation' => 'med',
            'name_en' => 'Medicine',
            'name_kh' => 'វេជ្ជសាស្ត្រ',
            'is_active' => true,
        ]);
        $secondMajor = Major::query()->create([
            'key' => 'biomedical_engineering',
            'abbreviation' => 'ABE',
            'name_en' => 'Biomedical Engineering',
            'name_kh' => 'វិស្វកម្មវេជ្ជជីវសាស្រ្ត',
            'is_active' => true,
        ]);
        Major::query()->create([
            'key' => 'not_selected',
            'abbreviation' => 'NS',
            'name_en' => 'Not Selected',
            'is_active' => true,
        ]);

        $closingDate = ClosingDate::query()->create([
            'name' => 'Application deadline',
            'type' => 'custom_form:1',
            'status' => ClosingDate::STATUS_OPEN,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ]);

        $closingDate->majors()->sync([$major->getKey(), $secondMajor->getKey()]);

        $this->assertSame(
            [$major->getKey(), $secondMajor->getKey()],
            $closingDate->fresh()->majors->modelKeys()
        );
        $this->assertSame('medicine_major', $major->key);
        $this->assertSame('MED', $major->abbreviation);
        app()->setLocale('en');
        $this->assertSame('Medicine', $major->localized_name);
        $this->assertSame('MED-Medicine', $major->localized_option_label);
        $this->assertSame('MED-Medicine', FormEntryData::majorLabel(['major' => (string) $major->getKey()]));
        $this->assertSame(
            'MED-Medicine, ABE-Biomedical Engineering',
            FormEntryData::majorLabel(['major' => [$major->getKey(), $secondMajor->getKey()]])
        );
        $this->assertSame('Medicine', Major::options()[$major->getKey()]);
        $this->assertSame('MED-Medicine', Major::options(includeAbbreviation: true)[$major->getKey()]);
        $this->assertSame([
            $secondMajor->getKey() => 'ABE-Biomedical Engineering',
            $major->getKey() => 'MED-Medicine',
        ], Major::optionsForCustomFormId(1));
        $selectOptionsMethod = new \ReflectionMethod(StudentDynamicFormSchema::class, 'getSelectOptions');
        $selectOptionsMethod->setAccessible(true);
        $this->assertSame(
            Major::optionsForCustomFormId(1),
            $selectOptionsMethod->invoke(
                app(StudentDynamicFormSchema::class),
                (object) ['custom_form_id' => 1],
                ['data_source_table' => 'majors'],
                null
            )
        );

        app()->setLocale('km');
        $this->assertSame('វេជ្ជសាស្ត្រ', $major->fresh()->localized_name);
        $this->assertSame('MED-វេជ្ជសាស្ត្រ', $major->fresh()->localized_option_label);
        $this->assertSame('វេជ្ជសាស្ត្រ', Major::options()[$major->getKey()]);
        $this->assertSame('MED-វេជ្ជសាស្ត្រ', Major::options(includeAbbreviation: true)[$major->getKey()]);
        $this->assertSame([
            $secondMajor->getKey() => 'ABE-វិស្វកម្មវេជ្ជជីវសាស្រ្ត',
            $major->getKey() => 'MED-វេជ្ជសាស្ត្រ',
        ], Major::optionsForCustomFormId(1));
    }

    public function test_majors_resource_is_admin_only(): void
    {
        $this->assertSame(Major::class, MajorResource::getModel());
        $this->assertSame('majors', MajorResource::getSlug());
    }

    public function test_major_can_be_saved_with_only_its_khmer_name(): void
    {
        $major = Major::query()->create([
            'key' => 'khmer_only_major',
            'abbreviation' => 'KHM',
            'name_en' => null,
            'name_kh' => 'មុខវិជ្ជាថ្មី',
            'is_active' => true,
        ]);

        $this->assertNull($major->fresh()->name_en);
        $this->assertSame('មុខវិជ្ជាថ្មី', $major->fresh()->localized_name);
        $this->assertDatabaseHas('majors', [
            'id' => $major->getKey(),
            'name_en' => null,
            'name_kh' => 'មុខវិជ្ជាថ្មី',
        ]);
    }

    public function test_document_renderer_displays_a_major_name_instead_of_its_numeric_id(): void
    {
        app()->setLocale('km');

        $major = Major::query()->create([
            'key' => 'medicine',
            'abbreviation' => 'MED',
            'name_en' => 'Medicine',
            'name_kh' => 'ប្រឡងថ្នាក់ជាតិចូល-បរិញ្ញាបត្ររង',
            'is_active' => true,
        ]);

        $renderer = new DocumentRenderer;
        $displayValueForKey = new \ReflectionMethod($renderer, 'displayValueForKey');
        $displayValueForKey->setAccessible(true);

        $this->assertSame(
            'ប្រឡងថ្នាក់ជាតិចូល-បរិញ្ញាបត្ររង',
            $displayValueForKey->invoke($renderer, 'major', (string) $major->getKey(), []),
        );
    }

    public function test_major_names_and_abbreviations_may_repeat_but_keys_must_be_unique(): void
    {
        Major::query()->create([
            'key' => 'public_health',
            'abbreviation' => 'MPH',
            'name_en' => 'Public Health',
            'name_kh' => 'សុខភាពសាធារណៈ',
            'is_active' => true,
        ]);
        Major::query()->create([
            'key' => 'public_health_2',
            'abbreviation' => 'MPH',
            'name_en' => 'Public Health',
            'name_kh' => 'សុខភាពសាធារណៈ',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('majors', 2);

        try {
            Major::query()->create([
                'key' => 'public_health',
                'abbreviation' => 'OTHER',
                'name_en' => 'Another Name',
                'is_active' => true,
            ]);
            $this->fail('A duplicate major key should be rejected by the database.');
        } catch (QueryException) {
            $this->assertDatabaseCount('majors', 2);
        }
    }

    public function test_major_options_can_be_filtered_by_degree_level(): void
    {
        $degreeLevel = DegreeLevel::query()->create([
            'key' => 'bachelor',
            'label_en' => 'Bachelor',
            'label_kh' => 'បរិញ្ញាបត្រ',
            'is_active' => true,
        ]);
        $linkedMajor = Major::query()->create([
            'key' => 'medicine',
            'abbreviation' => 'MED',
            'name_en' => 'Medicine',
            'is_active' => true,
        ]);
        $unlinkedMajor = Major::query()->create([
            'key' => 'nursing',
            'abbreviation' => 'NUR',
            'name_en' => 'Nursing',
            'is_active' => true,
        ]);

        $linkedMajor->degreeLevels()->attach($degreeLevel);

        $this->assertSame(
            [$linkedMajor->getKey() => 'Medicine'],
            Major::optionsForDegreeLevelId($degreeLevel->getKey())
        );
        $this->assertSame(
            [$linkedMajor->getKey() => 'MED-Medicine'],
            Major::optionsForDegreeLevelId($degreeLevel->getKey(), includeAbbreviation: true)
        );
        $this->assertSame([], Major::optionsForDegreeLevelId(null));
        $this->assertSame([], Major::optionsForDegreeLevelId('missing'));
        $this->assertDatabaseHas('degree_level_major', [
            'degree_level_id' => $degreeLevel->getKey(),
            'major_id' => $linkedMajor->getKey(),
        ]);
        $this->assertDatabaseMissing('degree_level_major', [
            'degree_level_id' => $degreeLevel->getKey(),
            'major_id' => $unlinkedMajor->getKey(),
        ]);
    }

    public function test_closing_date_major_options_respect_its_degree_level(): void
    {
        $degreeLevel = DegreeLevel::query()->create([
            'key' => 'master',
            'label_en' => 'Master',
            'is_active' => true,
        ]);
        $linkedMajor = Major::query()->create([
            'key' => 'public_health',
            'abbreviation' => 'MPH',
            'name_en' => 'Public Health',
            'is_active' => true,
        ]);
        $otherMajor = Major::query()->create([
            'key' => 'biomedical_engineering',
            'abbreviation' => 'ABE',
            'name_en' => 'Biomedical Engineering',
            'is_active' => true,
        ]);
        $linkedMajor->degreeLevels()->attach($degreeLevel);

        $closingDate = ClosingDate::query()->create([
            'name' => 'Master application',
            'type' => 'custom_form:42',
            'degree_level_id' => $degreeLevel->getKey(),
            'status' => ClosingDate::STATUS_OPEN,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ]);
        $closingDate->majors()->sync([$linkedMajor->getKey(), $otherMajor->getKey()]);

        $this->assertSame(
            [$linkedMajor->getKey() => 'MPH-Public Health'],
            Major::optionsForCustomFormId(42)
        );
        $this->assertSame($degreeLevel->getKey(), $closingDate->fresh()->degreeLevel->getKey());
    }
}
