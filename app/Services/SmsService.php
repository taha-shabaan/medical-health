<?php
namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;

class SmsService
{
    public function send(string $to, string $message): bool
    {
        $at = new AfricasTalking(
            config('services.sms.username'),
            config('services.sms.api_key')
        );

        $sms = $at->sms();
        $result = $sms->send([
            'to'      => $to,
            'message' => $message,
        ]);

        return $result['status'] === 'success';
    }
}