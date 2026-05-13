<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientInvoicesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_list_and_pay_invoice(): void
    {
        $patient = User::factory()->patient()->create();
        $doctor = User::factory()->doctor()->create(['is_active' => true]);
        $apt = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => Carbon::now()->addDay()->toDateString(),
            'appointment_time' => '10:00:00',
            'status' => 'pending',
        ]);
        Sanctum::actingAs($patient);

        $list = $this->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonStructure(['invoices', 'summary' => ['total', 'pending', 'paid']]);

        $id = $list->json('invoices.0.id');
        $this->assertIsString($id);
        $this->assertStringContainsString('INV', $id);

        $this->postJson("/api/invoices/{$id}/pay", [
            'paymentMethod' => 'card',
            'cardData' => [
                'number' => '4111111111111111',
            ],
        ])->assertStatus(200);

        $invoice = Invoice::where('appointment_id', $apt->id)->first();
        $this->assertNotNull($invoice);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
            'payment_method' => 'card',
        ]);
    }

    public function test_patient_can_get_invoice_by_reference(): void
    {
        $patient = User::factory()->patient()->create();
        $doctor = User::factory()->doctor()->create(['is_active' => true]);
        $apt = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => Carbon::now()->addDay()->toDateString(),
            'appointment_time' => '10:00:00',
            'status' => 'pending',
        ]);
        $invoice = Invoice::where('appointment_id', $apt->id)->first();
        $this->assertNotNull($invoice);

        Sanctum::actingAs($patient);
        $this->getJson('/api/invoices/'.$invoice->invoice_number)
            ->assertOk()
            ->assertJsonPath('invoice.id', $invoice->invoice_number);
    }
}
