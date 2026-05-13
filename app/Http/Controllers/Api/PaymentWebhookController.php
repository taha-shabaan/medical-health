<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.payments.webhook_secret', '');
        if ($secret === '') {
            return response()->json(['message' => 'Webhook is not configured.'], 503);
        }

        $signature = (string) $request->header('X-Payment-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $validated = $request->validate([
            'invoice_number' => ['required', 'string', 'max:32'],
            'status' => ['required', 'in:paid,failed'],
            'transaction_id' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['nullable', 'in:card,cash,vodafone'],
            'provider_payload' => ['nullable', 'array'],
        ]);

        $invoice = Invoice::where('invoice_number', strtoupper($validated['invoice_number']))->first();
        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        if ($validated['status'] === 'paid') {
            $invoice->update([
                'status' => 'paid',
                'payment_reference' => $validated['transaction_id'] ?? $invoice->payment_reference,
                'payment_method' => $validated['payment_method'] ?? $invoice->payment_method,
                'paid_at' => $invoice->paid_at ?? now(),
                'payment_details' => $validated['provider_payload'] ?? $invoice->payment_details,
            ]);
        }

        return response()->json(['message' => 'Webhook accepted']);
    }
}
