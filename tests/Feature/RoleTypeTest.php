<?php

namespace Tests\Feature;

use App\Models\RoleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_role_types_are_assigned_sequential_sort_orders(): void
    {
        $first = RoleType::query()->create([
            'key' => 'candidate',
            'label_en' => 'Candidate',
            'label_kh' => 'បេក្ខជន',
            'is_active' => true,
        ]);

        $second = RoleType::query()->create([
            'key' => 'staff',
            'label_en' => 'Staff',
            'label_kh' => 'បុគ្គលិក',
            'is_active' => true,
        ]);

        $this->assertSame(1, $first->sort_order);
        $this->assertSame(2, $second->sort_order);
    }

    public function test_explicit_sort_order_is_preserved(): void
    {
        $roleType = RoleType::query()->create([
            'key' => 'staff',
            'label_en' => 'Staff',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->assertSame(10, $roleType->sort_order);
    }
}
