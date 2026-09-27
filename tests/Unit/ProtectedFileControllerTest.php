<?php

namespace Tests\Unit;

use App\Http\Controllers\ProtectedFileController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProtectedFileControllerTest extends TestCase
{
    #[DataProvider('unsafePaths')]
    public function test_unsafe_file_paths_are_rejected(?string $path): void
    {
        $controller = new ProtectedFileController;
        $method = new ReflectionMethod($controller, 'normalizePath');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($controller, $path ?? ''));
    }

    public static function unsafePaths(): array
    {
        return [
            'empty' => [''],
            'absolute path' => ['/etc/passwd'],
            'traversal' => ['custom-form-uploads/../../secret.txt'],
            'external url' => ['https://example.test/file.pdf'],
        ];
    }
}
