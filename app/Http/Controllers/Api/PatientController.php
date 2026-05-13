<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PatientController extends Controller
{
    public function __construct(
        private readonly PaymentGateway $paymentGateway
    ) {}

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'height' => ['nullable', 'integer', 'min:80', 'max:230'],
            'blood_type' => ['nullable', 'string', 'max:8'],
            'weight' => ['nullable', 'string', 'max:12'],
            'governorate' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Patient profile updated successfully',
            'user' => $request->user()->fresh(),
        ]);
    }

    /**
     * Invoices for the current patient (same shape the dashboard expects, aligned with admin mapping).
     */
    public function listInvoices(Request $request): JsonResponse
    {
        $invoices = Invoice::query()
            ->where('patient_id', $request->user()->id)
            ->with([
                'doctor:id,name',
                'patient:id,name',
                'appointment:id,appointment_date,created_at',
            ])
            ->latest()
            ->get();

        $payload = $invoices->map(function (Invoice $invoice) {
            $appointmentDate = $invoice->appointment?->appointment_date;
            $dateStr = $appointmentDate instanceof \DateTimeInterface
                ? $appointmentDate->format('Y-m-d')
                : (is_string($appointmentDate) ? $appointmentDate : (string) ($invoice->created_at?->toDateString() ?? ''));

            return [
                'id' => $invoice->invoice_number,
                'invoiceNum' => '#'.$invoice->invoice_number,
                'patient' => $invoice->patient?->name ?? '-',
                'doctor' => $invoice->doctor?->name ?? '-',
                'service' => $invoice->service,
                'amount' => (float) $invoice->amount,
                'date' => $dateStr,
                'paymentMethod' => $invoice->payment_method ?: '---',
                'status' => $invoice->status === 'paid' ? 'تم الدفع' : 'لم يتم الدفع',
            ];
        })->values();

        $pending = $payload->where('status', 'لم يتم الدفع');
        $paid = $payload->where('status', 'تم الدفع');

        return response()->json([
            'invoices' => $payload,
            'summary' => [
                'total' => $payload->count(),
                'pending' => $pending->sum('amount'),
                'paid' => $paid->sum('amount'),
            ],
        ]);
    }

    public function invoiceByRef(Request $request, string $invoiceRef): JsonResponse
    {
        $invoice = Invoice::query()
            ->where('patient_id', $request->user()->id)
            ->where('invoice_number', strtoupper($invoiceRef))
            ->with([
                'doctor:id,name',
                'patient:id,name',
                'appointment:id,appointment_date,created_at',
            ])
            ->first();

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        $appointmentDate = $invoice->appointment?->appointment_date;
        $dateStr = $appointmentDate instanceof \DateTimeInterface
            ? $appointmentDate->format('Y-m-d')
            : (is_string($appointmentDate) ? $appointmentDate : (string) ($invoice->created_at?->toDateString() ?? ''));

        return response()->json([
            'invoice' => [
                'id' => $invoice->invoice_number,
                'invoiceNum' => '#'.$invoice->invoice_number,
                'patient' => $invoice->patient?->name ?? '-',
                'doctor' => $invoice->doctor?->name ?? '-',
                'service' => $invoice->service,
                'amount' => (float) $invoice->amount,
                'date' => $dateStr,
                'paymentMethod' => $invoice->payment_method ?: '---',
                'status' => $invoice->status === 'paid' ? 'تم الدفع' : 'لم يتم الدفع',
            ],
        ]);
    }

    public function payInvoice(Request $request, string $invoiceRef): JsonResponse
    {
        $validated = $request->validate([
            'paymentMethod' => ['required', 'in:card,cash,vodafone'],
            'cardData' => ['nullable', 'array'],
        ]);

        $invoice = Invoice::query()
            ->where('patient_id', $request->user()->id)
            ->where('invoice_number', strtoupper($invoiceRef))
            ->first();

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        if ($invoice->status === 'paid') {
            return response()->json(['message' => 'Already paid.'], 200);
        }

        if (
            $validated['paymentMethod'] === 'card' &&
            empty($validated['cardData']['number'])
        ) {
            throw ValidationException::withMessages(['cardData.number' => ['Card number is required for card payments.']]);
        }

        try {
            $charge = $this->paymentGateway->charge(
                $invoice,
                $validated['paymentMethod'],
                $validated['cardData'] ?? null
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        if (! ($charge['success'] ?? false)) {
            return response()->json([
                'message' => $charge['error'] ?? 'Payment failed.',
            ], 422);
        }

        if (($charge['requires_action'] ?? false) && ! empty($charge['payment_url'])) {
            $invoice->update([
                'payment_method' => $validated['paymentMethod'],
                'payment_reference' => (string) ($charge['transaction_id'] ?? $invoice->payment_reference),
                'payment_details' => array_filter([
                    'pending_confirmation' => true,
                    'payment_url' => $charge['payment_url'],
                    'provider_card' => $validated['cardData'] ?? null,
                ]),
            ]);

            return response()->json([
                'message' => 'Payment initiated. Awaiting confirmation.',
                'pending_confirmation' => true,
                'payment_url' => $charge['payment_url'],
                'invoice' => [
                    'id' => $invoice->invoice_number,
                    'status' => 'unpaid',
                    'paymentMethod' => $invoice->payment_method,
                ],
            ], 202);
        }

        $invoice->update([
            'status' => 'paid',
            'payment_method' => $validated['paymentMethod'],
            'paid_at' => now(),
            'payment_reference' => (string) ($charge['transaction_id'] ?? 'PAY-'.strtoupper(bin2hex(random_bytes(4)))),
            'payment_details' => $validated['cardData'] ?? null,
        ]);

        return response()->json([
            'message' => 'Payment successful',
            'invoice' => [
                'id' => $invoice->invoice_number,
                'status' => 'paid',
                'paymentMethod' => $invoice->payment_method,
                'paidAt' => $invoice->paid_at,
            ],
        ]);
    }
}
