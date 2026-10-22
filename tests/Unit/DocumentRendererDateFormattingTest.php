<?php

namespace Tests\Unit;

use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use ReflectionMethod;
use Tests\TestCase;

class DocumentRendererDateFormattingTest extends TestCase
{
    public function test_iso_date_placeholders_use_khmer_month_names(): void
    {
        $renderer = new DocumentRenderer;
        $displayValueForKey = new ReflectionMethod($renderer, 'displayValueForKey');
        $displayValueForKey->setAccessible(true);

        $this->assertSame(
            '02-តុលា-2026',
            $displayValueForKey->invoke($renderer, 'mnmm', '2026-10-02', []),
        );
    }
}
