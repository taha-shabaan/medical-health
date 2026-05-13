<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaymentGateway
{
    /**
     * @return array{
     *   success:bool,
     *   transaction_id?:string,
     *   error?:string,
     *   requires_action?:bool,
     *   payment_url?:string
     * }
     */
    public function charge(Invoice $invoice, string $paymentMethod, ?array $cardData = null): array
    {
        $driver = (string) config('services.payments.driver', 'fake');

        if ($driver === 'fake') {
            return [
                'success' => true,
                'transaction_id' => 'FAKE-'.strtoupper(bin2hex(random_bytes(4))),
            ];
        }

        if ($driver !== 'rest') {
            throw new RuntimeException('Unsupported payment gateway driver.');
        }

        $endpoint = (string) config('services.payments.endpoint');
        $apiKey = (string) config('services.payments.api_key');
        $timeout = (int) config('services.payments.timeout', 15);

        if ($endpoint === '' || $apiKey === '') {
            throw new RuntimeException('Payment gateway is not configured.');
        }

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withToken($apiKey)
                ->post(rtrim($endpoint, '/'), [
                    'amount' => (float) $invoice->amount,
                    'currency' => $invoice->currency ?: 'EGP',
                    'invoice_number' => $invoice->invoice_number,
                    'patient_id' => $invoice->patient_id,
                    'doctor_id' => $invoice->doctor_id,
                    'payment_method' => $paymentMethod,
                    'card' => $cardData,
                ]);
        } catch (ConnectionException $e) {
            return ['success' => false, 'error' => 'Payment gateway is unreachable.'];
        }

        if (! $response->successful()) {
            return ['success' => false, 'error' => 'Payment was rejected by provider.'];
        }

        $json = $response->json();
        $ok = (bool) data_get($json, 'success', false);
        $requiresAction = (bool) data_get($json, 'requires_action', false);

        return [
            'success' => $ok,
            'transaction_id' => (string) data_get($json, 'transaction_id', ''),
            'requires_action' => $requiresAction,
            'payment_url' => (string) data_get($json, 'payment_url', ''),
            'error' => $ok ? null : (string) data_get($json, 'message', 'Payment failed.'),
        ];
    }
}
