<?php

namespace App\Support;

use App\Models\UserNotification;

class InAppNotification
{
    public static function send(int $userId, string $title, ?string $body = null, string $type = 'general', array $data = []): UserNotification
    {
        return UserNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
        ]);
    }
}
