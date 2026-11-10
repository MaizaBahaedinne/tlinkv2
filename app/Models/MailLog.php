<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

/**
 * Journal des e-mails envoyés par la plateforme, une ligne par destinataire.
 * Le message lui-même n'est pas conservé : il peut contenir un code de confirmation ou un lien personnel.
 */
class MailLog extends Model
{
    public const HEADER = 'X-Tlink-Log';

    public const TEMPLATE_HEADER = 'X-Tlink-Template';

    public const STATUSES = ['pending' => 'En cours', 'sent' => 'Envoyé', 'failed' => 'Échec'];

    /** Envois démarrés dans cette requête et pas encore confirmés : ils passent en échec si l'envoi lève une exception. */
    private static array $inFlight = [];

    protected $fillable = ['to_email', 'to_name', 'from_email', 'subject', 'template', 'status', 'error', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    /** Un envoi reste « en cours » jusqu'à la confirmation du serveur : passé ce délai, on le considère en échec. */
    public function effectiveStatus(): string
    {
        if ($this->status === 'pending' && $this->created_at?->lt(now()->subMinutes(5))) {
            return 'failed';
        }

        return $this->status;
    }

    public static function recordSending(MessageSending $event): void
    {
        try {
            $message = $event->message;
            $ids = [];

            foreach ($message->getTo() as $address) {
                $log = static::query()->create([
                    'to_email' => mb_strtolower($address->getAddress()),
                    'to_name' => $address->getName() ?: null,
                    'from_email' => $message->getFrom()[0]?->getAddress(),
                    'subject' => Str::limit(preg_replace('/\b\d{6}\b/', '••••••', (string) $message->getSubject()), 250, ''),
                    'template' => static::templateOf($message),
                    'status' => 'pending',
                ]);

                $ids[] = $log->id;
                static::$inFlight[$log->id] = true;
            }

            if ($ids !== []) {
                $message->getHeaders()->addTextHeader(self::HEADER, implode(',', $ids));
            }
        } catch (\Throwable $exception) {
            // Le journal ne doit jamais empêcher un e-mail de partir.
            report($exception);
        }
    }

    public static function recordSent(MessageSent $event): void
    {
        try {
            $header = $event->message->getHeaders()->get(self::HEADER);
            $ids = $header ? array_filter(array_map('intval', explode(',', $header->getBodyAsString()))) : [];

            if ($ids === []) {
                return;
            }

            static::query()->whereIn('id', $ids)->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);

            foreach ($ids as $id) {
                unset(static::$inFlight[$id]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** Marque en échec les envois de cette requête qui n'ont pas abouti. */
    public static function failInFlight(\Throwable $exception): void
    {
        if (static::$inFlight === []) {
            return;
        }

        try {
            static::query()->whereIn('id', array_keys(static::$inFlight))->where('status', 'pending')
                ->update(['status' => 'failed', 'error' => Str::limit(strip_tags($exception->getMessage()), 500, '')]);
        } catch (\Throwable) {
            // Table absente ou base indisponible : rien à journaliser.
        }

        static::$inFlight = [];
    }

    private static function templateOf(Email $message): ?string
    {
        $header = $message->getHeaders()->get(self::TEMPLATE_HEADER);

        return $header ? Str::limit($header->getBodyAsString(), 60, '') : null;
    }
}
