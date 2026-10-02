<?php

namespace Tests\Feature;

use App\Models\UserType;
use App\Support\UserTypeOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class UserTypeOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_initialization_does_not_reactivate_a_closed_candidate_type(): void
    {
        $record = UserTypeOptions::defaultRecords()[0];
        $candidateType = UserType::query()->create($record);
        $candidateType->update(['is_active' => false]);

        $this->resetUserTypeOptionsState();

        UserTypeOptions::options();

        $candidateType->refresh();

        $this->assertFalse($candidateType->is_active);
        $this->assertArrayNotHasKey($candidateType->key, UserTypeOptions::options());
        $this->assertFalse(UserTypeOptions::isActiveCandidateTypeRole($candidateType->key));
    }

    protected function tearDown(): void
    {
        $this->resetUserTypeOptionsState();

        parent::tearDown();
    }

    private function resetUserTypeOptionsState(): void
    {
        $reflection = new ReflectionClass(UserTypeOptions::class);

        $defaultsEnsured = $reflection->getProperty('defaultsEnsured');
        $defaultsEnsured->setValue(null, false);

        $optionsCache = $reflection->getProperty('optionsCache');
        $optionsCache->setValue(null, []);

        $candidateManagedRoleKeysCache = $reflection->getProperty('candidateManagedRoleKeysCache');
        $candidateManagedRoleKeysCache->setValue(null, null);
    }
}
