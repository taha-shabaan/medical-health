<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_webhook_marks_invoice_paid_with_valid_signature(): void
    {
        config()->set('services.payments.webhook_secret', 'secret123');

        $patient = User::factory()->patient()->create();
        $doctor = User::factory()->doctor()->create();

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00:00',
            'status' => 'pending',
        ]);

        $invoice = Invoice::where('appointment_id', $appointment->id)->first();
        $this->assertNotNull($invoice);

        $payload = [
            'invoice_number' => $invoice->invoice_number,
            'status' => 'paid',
            'transaction_id' => 'TX-123456',
            'payment_method' => 'card',
            'provider_payload' => ['provider' => 'fake'],
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', (string) $raw, 'secret123');

        $this->withHeader('X-Payment-Signature', $signature)
            ->postJson('/api/payments/webhook', $payload)
            ->assertOk();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
            'payment_reference' => 'TX-123456',
            'payment_method' => 'card',
        ]);
    }

    public function test_payment_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.payments.webhook_secret', 'secret123');

        $this->withHeader('X-Payment-Signature', 'bad-signature')
            ->postJson('/api/payments/webhook', [
                'invoice_number' => 'INV-0001',
                'status' => 'paid',
            ])
            ->assertStatus(401);
    }
}
