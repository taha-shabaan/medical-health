<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_cannot_access_admin_routes(): void
    {
        $patient = User::factory()->patient()->create();

        Sanctum::actingAs($patient);

        $this->getJson('/api/admin/doctors')
            ->assertStatus(403);
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/doctors')
            ->assertOk()
            ->assertJsonStructure(['doctors']);
    }
}
