<?php

require dirname(__DIR__).'/vendor/autoload.php';

// Path repositories do not load their dev namespaces through the host autoloader.
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Chanthoeun\\FilamentCustomForms\\Tests\\' => dirname(__DIR__).'/packages/chanthoeun/filament-custom-forms/tests/',
        'Chanthoeun\\FilamentCustomForms\\Database\\Factories\\' => dirname(__DIR__).'/packages/chanthoeun/filament-custom-forms/database/factories/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $path = $directory.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
    }
});
