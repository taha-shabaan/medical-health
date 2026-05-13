<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_search_doctors(): void
    {
        User::factory()->doctor()->create(['name' => 'Dr Searchable Unique', 'is_active' => true]);
        $patient = User::factory()->patient()->create();
        Sanctum::actingAs($patient);

        $this->getJson('/api/doctors/search?q=Searchable')
            ->assertOk()
            ->assertJsonStructure(['doctors']);
    }

    public function test_patient_can_view_doctor_slots(): void
    {
        $doctor = User::factory()->doctor()->create(['is_active' => true]);
        $patient = User::factory()->patient()->create();
        Sanctum::actingAs($patient);

        $this->getJson("/api/doctors/{$doctor->id}/slots")
            ->assertOk()
            ->assertJsonStructure(['days', 'doctor']);
    }

    public function test_patient_can_filter_doctors_by_specialty(): void
    {
        User::factory()->doctor()->create(['specialty' => 'باطنة']);
        User::factory()->doctor()->create(['specialty' => 'طب الأسنان']);
        $patient = User::factory()->patient()->create();
        Sanctum::actingAs($patient);

        $this->getJson('/api/doctors/search?specialty=باطنة')
            ->assertOk()
            ->assertJsonCount(1, 'doctors')
            ->assertJsonPath('doctors.0.specialty', 'باطنة');
    }
}
