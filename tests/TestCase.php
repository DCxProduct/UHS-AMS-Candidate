<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static ?string $testStoragePath = null;

    public function createApplication()
    {
        $this->configureTestingStoragePath();

        return parent::createApplication();
    }

    protected function configureTestingStoragePath(): void
    {
        if (self::$testStoragePath === null) {
            self::$testStoragePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'uhs-ams-candidate-test-storage-'
                . getmypid();

            register_shutdown_function(static function (): void {
                if (self::$testStoragePath === null || ! is_dir(self::$testStoragePath)) {
                    return;
                }

                self::deleteDirectory(self::$testStoragePath);
            });
        }

        foreach ([
            self::$testStoragePath,
            self::$testStoragePath . '/app/private',
            self::$testStoragePath . '/app/public',
            self::$testStoragePath . '/framework/cache',
            self::$testStoragePath . '/framework/data',
            self::$testStoragePath . '/framework/sessions',
            self::$testStoragePath . '/framework/testing',
            self::$testStoragePath . '/framework/views',
            self::$testStoragePath . '/logs',
        ] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }

        $_ENV['LARAVEL_STORAGE_PATH'] = self::$testStoragePath;
        $_SERVER['LARAVEL_STORAGE_PATH'] = self::$testStoragePath;
    }

    private static function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory) || is_link($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path) && ! is_link($path)) {
                self::deleteDirectory($path);

                continue;
            }

            if (file_exists($path) || is_link($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
