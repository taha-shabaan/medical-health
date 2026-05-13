<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicalRecordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_create_or_update_medical_record(): void
    {
        $patient = User::factory()->patient()->create();
        Sanctum::actingAs($patient);

        $this->putJson('/api/medical-records', [
            'allergies' => 'Peanuts',
            'chronic_conditions' => '—',
            'blood_pressure' => '120/80',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Medical record updated successfully');
    }

    public function test_doctor_cannot_access_patient_medical_record_endpoint(): void
    {
        $doctor = User::factory()->doctor()->create();
        Sanctum::actingAs($doctor);

        $this->getJson('/api/medical-records')
            ->assertStatus(403);
    }
}
