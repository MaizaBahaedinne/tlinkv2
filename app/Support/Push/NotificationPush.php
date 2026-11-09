<?php

namespace App\Support\Push;

use App\Models\AppNotification;
use App\Models\PushDevice;
use App\Support\Domains;

/**
 * Notification push d'une notification T.Link (décision, commande, badge…), après l'envoi de la réponse.
 */
final class NotificationPush
{
    public static function notify(AppNotification $notification): void
    {
        if (! Fcm::enabled() && ! WebPush::enabled()) {
            return;
        }

        $userId = (int) $notification->user_id;
        $title = (string) $notification->title;
        $body = (string) $notification->body;
        $notificationId = (int) $notification->id;
        // Les notifications T.Link s'ouvrent dans T.Link, et ne sont envoyées qu'aux appareils de T.Link.
        $url = Domains::url('app', route('notifications.index', [], false));

        app()->terminating(function () use ($userId, $title, $body, $notificationId, $url): void {
            try {
                $devices = PushDevice::query()->where('user_id', $userId)->forSection('app')->get();

                if ($devices->isNotEmpty()) {
                    Fcm::send($devices, $title, $body, ['url' => $url], 'notification-'.$notificationId, 'tlink');
                }

                WebPush::sendToUsers([$userId], $title, $body, $url, 'notification-'.$notificationId);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
