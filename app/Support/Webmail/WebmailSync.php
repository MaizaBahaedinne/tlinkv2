<?php

namespace App\Support\Webmail;

use App\Models\AppNotification;
use App\Models\WebmailMailbox;
use Illuminate\Support\Str;

/**
 * Relève les boîtes : nombre de messages non lus (pastille du menu) et notification des lecteurs autorisés à chaque
 * nouveau message. La première relève d'une boîte ne notifie rien : elle fixe seulement le point de départ.
 */
final class WebmailSync
{
    /** @return array{checked: int, notified: int, failed: int} */
    public static function run(?GatewayFactory $gateways = null): array
    {
        $gateways ??= app(GatewayFactory::class);
        $result = ['checked' => 0, 'notified' => 0, 'failed' => 0];

        WebmailMailbox::query()->where('is_active', true)->each(function (WebmailMailbox $mailbox) use ($gateways, &$result): void {
            try {
                $state = $gateways->make($mailbox)->inboxState((int) $mailbox->last_uid);
            } catch (\Throwable $exception) {
                $mailbox->forceFill(['last_error' => Str::limit($exception->getMessage(), 240, ''), 'checked_at' => now()])->save();
                $result['failed']++;

                return;
            }

            $result['checked']++;

            if ((int) $mailbox->last_uid > 0 && $state['new'] !== []) {
                $result['notified'] += self::notify($mailbox, $state['new']);
            }

            $mailbox->forceFill(['last_uid' => $state['last_uid'], 'unseen' => $state['unseen'], 'checked_at' => now(), 'last_error' => null])->save();
        });

        return $result;
    }

    /** @param array<int, array<string, mixed>> $new */
    private static function notify(WebmailMailbox $mailbox, array $new): int
    {
        $count = count($new);
        $first = $new[0];
        $sender = ($first['from']['name'] ?? '') ?: ($first['from']['email'] ?? '');

        $title = $count === 1 ? 'Nouveau message — '.$mailbox->title() : $count.' nouveaux messages — '.$mailbox->title();
        $body = $count === 1 ? Str::limit($sender.' : '.$first['subject'], 160) : Str::limit('Dernier : '.$sender.' : '.$first['subject'], 160);
        $url = '/admin/webmail?boite='.$mailbox->id;
        $readers = WebmailAccess::readers($mailbox);

        foreach ($readers as $userId) {
            AppNotification::query()->create(['user_id' => $userId, 'type' => 'webmail', 'title' => $title, 'body' => $body, 'data' => ['url' => $url]]);
        }

        return count($readers);
    }
}
