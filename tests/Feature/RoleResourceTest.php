<?php

namespace Tests\Feature;

use App\Models\Role;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    public function test_role_edit_title_uses_english_label_for_english_locale(): void
    {
        App::setLocale('en');

        $role = new Role([
            'name' => 'data_entry',
            'label_en' => 'Data Entry',
            'name_kh' => 'អ្នកបញ្ចូលទិន្នន័យ',
        ]);

        $this->assertSame('Data Entry', RoleResource::getRecordTitle($role));
        $this->assertNotSame('data_entry', RoleResource::getRecordTitle($role));
    }

    public function test_role_edit_title_uses_khmer_label_for_khmer_locale(): void
    {
        App::setLocale('km');

        $role = new Role([
            'name' => 'data_entry',
            'label_en' => 'Data Entry',
            'name_kh' => 'អ្នកបញ្ចូលទិន្នន័យ',
        ]);

        $this->assertSame('អ្នកបញ្ចូលទិន្នន័យ', RoleResource::getRecordTitle($role));
        $this->assertNotSame('data_entry', RoleResource::getRecordTitle($role));
    }
}
