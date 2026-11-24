<?php

namespace Tests\Feature;

use App\Support\CandidateProfilePayload;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CandidateProfilePayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('custom_forms', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
        });
        Schema::create('custom_form_fields', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('custom_form_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('type');
            $table->boolean('required')->default(false);
            $table->json('options');
        });
        $id = DB::table('custom_forms')->insertGetId(['slug' => 'profile']);
        foreach (['father', 'mother'] as $parent) {
            foreach (['ethnicity', 'nationality', 'status'] as $attribute) {
                DB::table('custom_form_fields')->insert([
                    'custom_form_id' => $id, 'name' => $parent.'_'.$attribute, 'type' => 'select_dropdown',
                    'options' => json_encode(['choices' => $attribute === 'status'
                        ? ['alive' => ['en' => 'Alive', 'km' => 'នៅរស់'], 'deceased' => ['en' => 'Deceased', 'km' => 'ទទួលមរណភាព']]
                        : ['khmer' => ['en' => 'Khmer', 'km' => 'ខ្មែរ']]]),
                ]);
            }
        }
    }

    public static function parentAnswers(): array
    {
        return [
            'canonical keys' => ['khmer', 'alive', 'alive'],
            'Khmer labels' => ['ខ្មែរ', 'នៅរស់', 'alive'],
            'different deceased labels in Candidate and AMS' => ['ខ្មែរ', 'ទទួលមរណភាព', 'deceased'],
            'English labels with whitespace' => [' Khmer ', ' DECEASED ', 'deceased'],
        ];
    }

    #[DataProvider('parentAnswers')]
    public function test_payload_sends_canonical_keys_for_parent_choices(string $ethnicity, string $status, string $expectedStatus): void
    {
        $data = $expected = [];
        foreach (['father', 'mother'] as $parent) {
            foreach (['ethnicity', 'nationality', 'status'] as $attribute) {
                $data[$parent.'_'.$attribute] = $attribute === 'status' ? $status : $ethnicity;
                $expected[$parent.'_'.$attribute] = $attribute === 'status' ? $expectedStatus : 'khmer';
            }
        }
        $payload = CandidateProfilePayload::make($data, []);
        $this->assertSame($expected, $payload['data']);
        $this->assertSame($status, $data['mother_status']);
    }

    public function test_unanswered_parent_fields_are_omitted_from_the_sync_payload(): void
    {
        $payload = CandidateProfilePayload::make(['father_status' => null, 'mother_status' => ' '], []);
        $this->assertSame([], $payload['data']);
    }
}
