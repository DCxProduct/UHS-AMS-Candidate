<?php

namespace Tests\Unit;

use Database\Seeders\AdminUserSeeder;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    public function test_non_production_seeding_keeps_existing_local_test_password(): void
    {
        $method = new ReflectionMethod(AdminUserSeeder::class, 'seededPassword');

        $this->assertSame(
            '1234567a',
            $method->invoke(new AdminUserSeeder, 'SEEDED_ADMIN_PASSWORD'),
        );
    }

    public function test_production_seeding_requires_explicit_password_configuration(): void
    {
        $previousEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $method = new ReflectionMethod(AdminUserSeeder::class, 'seededPassword');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('SEEDED_ADMIN_PASSWORD');

            $method->invoke(new AdminUserSeeder, 'SEEDED_ADMIN_PASSWORD');
        } finally {
            $this->app['env'] = $previousEnvironment;
        }
    }
}
