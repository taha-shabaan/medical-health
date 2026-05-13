<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_create_report_and_prescription(): void
    {
        $doctor = User::factory()->doctor()->create();
        Sanctum::actingAs($doctor);

        $this->postJson('/api/doctor/reports', [
            'patient_name' => 'John',
            'type' => 'lab',
            'details' => 'Blood work',
        ])->assertCreated()
            ->assertJsonPath('message', 'Report created successfully');

        $this->postJson('/api/doctor/prescriptions', [
            'patient_name' => 'John',
            'drug' => 'Aspirin',
        ])->assertCreated()
            ->assertJsonPath('message', 'Prescription created successfully');
    }

    public function test_doctor_cannot_delete_another_doctor_prescription(): void
    {
        $a = User::factory()->doctor()->create();
        $b = User::factory()->doctor()->create();

        $rx = Prescription::create([
            'doctor_id' => $a->id,
            'patient_name' => 'X',
            'drug' => 'X',
            'status' => 'active',
        ]);

        Sanctum::actingAs($b);

        $this->deleteJson("/api/doctor/prescriptions/{$rx->id}")
            ->assertStatus(403);
    }

    public function test_doctor_can_create_appointment_for_patient(): void
    {
        $doctor = User::factory()->doctor()->create();
        $patient = User::factory()->patient()->create();
        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => now()->subDay()->toDateString(),
            'appointment_time' => '10:00:00',
            'status' => 'completed',
            'notes' => null,
        ]);

        Sanctum::actingAs($doctor);

        $this->postJson('/api/doctor/appointments', [
            'patient_id' => $patient->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '14:30',
            'notes' => 'Follow-up',
        ])->assertCreated()
            ->assertJsonPath('appointment.patient_id', $patient->id)
            ->assertJsonPath('appointment.doctor_id', $doctor->id)
            ->assertJsonPath('appointment.status', 'confirmed');

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_doctor_can_register_patient_via_dashboard(): void
    {
        $doctor = User::factory()->doctor()->create();
        Sanctum::actingAs($doctor);

        $this->postJson('/api/doctor/patients', [
            'name' => 'New Patient',
            'email' => 'newpatient@example.com',
            'password' => 'password123',
            'gender' => 'male',
        ])->assertCreated()
            ->assertJsonPath('message', 'Patient created successfully');

        $this->assertDatabaseHas('users', [
            'email' => 'newpatient@example.com',
            'role' => 'patient',
        ]);

        $patient = User::where('email', 'newpatient@example.com')->first();
        $this->assertNotNull($patient);

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);
    }

    public function test_doctor_cannot_create_appointment_for_non_patient_user(): void
    {
        $doctor = User::factory()->doctor()->create();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($doctor);

        $this->postJson('/api/doctor/appointments', [
            'patient_id' => $admin->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '11:00',
        ])->assertStatus(422);
    }
}
