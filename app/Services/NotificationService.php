<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

class NotificationService
{
    public function notify(?User $user, string $type, string $message): ?Notification
    {
        if (! $user) {
            return null;
        }

        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'message' => $message,
            'is_read' => false,
        ]);
    }

    /**
     * Send a notification only if this user has never received one with the same reference.
     * Returns null when it was already sent (so callers can safely run more than once).
     */
    public function notifyOnce(?User $user, string $type, string $reference, string $message): ?Notification
    {
        if (! $user || Notification::where('user_id', $user->id)->where('reference', $reference)->exists()) {
            return null;
        }

        try {
            return Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'reference' => $reference,
                'message' => $message,
                'is_read' => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null; // sent by another request at the same moment
        }
    }
}
