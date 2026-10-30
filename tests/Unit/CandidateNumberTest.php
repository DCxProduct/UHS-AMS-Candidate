<?php

namespace Tests\Unit;

use App\Support\CandidateNumber;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class CandidateNumberTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            'current year' => ['BNM260001', 'BNM-0001'],
            'other year' => ['BNM270001', 'BNM-0001'],
            'other major' => ['BBN260012', 'BBN-0012'],
            'long sequence' => ['BNM2610000', 'BNM-10000'],
            'lowercase prefix' => ['bnm260001', 'BNM-0001'],
            'already formatted' => ['BNM-0001', 'BNM-0001'],
            'custom identifier' => ['CUSTOM-ID-17', 'CUSTOM-ID-17'],
            'short unrecognized identifier' => ['BNM26001', 'BNM26001'],
            'numeric custom identifier' => ['12345678', '12345678'],
            'placeholder' => ['-', '-'],
            'null' => [null, ''],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('numbers')]
    public function test_candidate_number_format_preserves_sequence_and_custom_identifiers(mixed $stored, string $visible): void
    {
        $this->assertSame($visible, CandidateNumber::format($stored));
    }

    public function test_document_candidate_number_placeholders_use_the_visible_format(): void
    {
        $renderer = new DocumentRenderer;
        $display = new ReflectionMethod($renderer, 'displayValueForKey');

        foreach (['candidate_id', 'candidate_identifier', 'application.candidate_id'] as $key) {
            $this->assertSame('BNM-0001', $display->invoke($renderer, $key, 'BNM260001', []));
        }

        $this->assertSame('BNM260001', $display->invoke($renderer, 'receipt_number', 'BNM260001', []));
    }
}
