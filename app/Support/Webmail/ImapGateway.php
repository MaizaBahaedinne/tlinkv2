<?php

namespace App\Support\Webmail;

use App\Models\WebmailMailbox;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

/**
 * Passerelle réelle vers la boîte d'une entité : IMAP (bibliothèque PHP pure, sans l'extension imap) pour lire et
 * classer, SMTP (Symfony Mailer) pour envoyer. Les identifiants viennent de la boîte (déchiffrés à la volée) et des
 * réglages du webmail ; aucun message n'est copié dans la base de T.Link.
 */
class ImapGateway implements MailGateway
{
    private ?Client $client = null;

    /** @var array<string, Folder> */
    private array $folderCache = [];

    public function __construct(private readonly WebmailMailbox $mailbox)
    {
    }

    public function ping(): void
    {
        $this->client();
    }

    public function folders(): array
    {
        $rows = [];

        foreach ($this->client()->getFolders(false, null, true) as $folder) {
            if ($folder->no_select ?? false) {
                continue;
            }

            try {
                $status = $folder->status();
            } catch (\Throwable) {
                $status = [];
            }

            $rows[] = [
                'path' => $folder->path,
                'name' => $this->displayName($folder),
                'role' => FolderRoles::detect((string) $folder->full_name),
                'unseen' => (int) ($status['unseen'] ?? 0),
                'total' => (int) ($status['messages'] ?? 0),
            ];
        }

        return FolderRoles::sort($rows);
    }

    public function messages(string $folder, int $page, int $perPage, ?string $search = null, ?array $uids = null): array
    {
        $target = $this->folder($folder);

        if ($uids !== null && $uids === []) {
            return ['items' => [], 'total' => 0];
        }

        $query = fn () => $target->query()->leaveUnread()->setFetchBody(false)->setFetchFlags(true)->softFail()
            ->when($uids !== null, fn ($query) => $query->whereUidIn($uids), fn ($query) => $query->whereAll())
            ->when($search !== null && $search !== '', fn ($query) => $query->whereText($search));

        $total = (int) $query()->count();
        $messages = $query()->setFetchOrderDesc()->limit($perPage, $page)->get();

        $items = [];

        foreach ($messages as $message) {
            $items[] = $this->summary($message);
        }

        usort($items, fn (array $a, array $b): int => $b['uid'] <=> $a['uid']);

        return ['items' => $items, 'total' => $total];
    }

    public function message(string $folder, int $uid): ?array
    {
        $message = $this->fetch($folder, $uid);

        if ($message === null) {
            return null;
        }

        $attachments = [];

        foreach ($message->getAttachments()->values() as $index => $attachment) {
            $attachments[] = ['index' => $index, 'name' => (string) ($attachment->name ?: 'piece-jointe'), 'mime' => (string) ($attachment->getMimeType() ?: 'application/octet-stream'), 'size' => (int) ($attachment->size ?? 0)];
        }

        return $this->summary($message) + [
            'html' => $message->hasHTMLBody() ? $message->getHTMLBody() : null,
            'text' => $message->hasTextBody() ? $message->getTextBody() : null,
            'message_id' => (string) $message->get('message_id'),
            'references' => (string) $message->get('references'),
            'attachments' => $attachments,
        ];
    }

    public function attachment(string $folder, int $uid, int $index): ?array
    {
        $message = $this->fetch($folder, $uid);
        $attachment = $message?->getAttachments()->values()[$index] ?? null;

        return $attachment === null ? null : [
            'name' => (string) ($attachment->name ?: 'piece-jointe'),
            'mime' => (string) ($attachment->getMimeType() ?: 'application/octet-stream'),
            'content' => (string) $attachment->content,
        ];
    }

    public function mark(string $folder, int $uid, string $flag, bool $on): void
    {
        $message = $this->fetch($folder, $uid, false);
        $name = ['seen' => 'Seen', 'flagged' => 'Flagged', 'answered' => 'Answered'][$flag] ?? null;

        if ($message === null || $name === null) {
            return;
        }

        $on ? $message->setFlag($name) : $message->unsetFlag($name);
    }

    public function move(string $folder, int $uid, string $target): void
    {
        $message = $this->fetch($folder, $uid, false);
        $message?->move($this->folder($target)->path, true);
    }

    public function trash(string $folder, int $uid): void
    {
        $message = $this->fetch($folder, $uid, false);

        if ($message === null) {
            return;
        }

        $trash = collect($this->folders())->firstWhere('role', 'trash');

        if ($trash === null || $trash['path'] === $folder) {
            $message->delete(true);

            return;
        }

        $message->move($trash['path'], true);
    }

    public function saveDraft(array $message, ?int $replaceUid = null): void
    {
        $draft = collect($this->folders())->firstWhere('role', 'drafts');

        if ($draft === null) {
            throw new WebmailException('Cette boîte n’a pas de dossier « Brouillons ».');
        }

        $this->folder($draft['path'])->appendMessage($this->email($message, true)->toString(), ['\\Draft', '\\Seen']);

        if ($replaceUid !== null) {
            $this->fetch($draft['path'], $replaceUid, false)?->delete(true);
        }
    }

    public function send(array $message): void
    {
        $config = WebmailSettings::get();
        $email = $this->email($message);

        $scheme = $config['smtp_port'] === 465 ? 'smtps' : 'smtp';
        $dsn = $scheme.'://'.rawurlencode($this->mailbox->address).':'.rawurlencode((string) $this->mailbox->password).'@'.$config['smtp_host'].':'.$config['smtp_port'];

        try {
            (new Mailer(Transport::fromDsn($dsn)))->send($email);
        } catch (\Throwable $exception) {
            throw new WebmailException('L’envoi a échoué : '.Str::limit(strip_tags($this->scrub($exception->getMessage())), 200));
        }

        // La copie dans « Envoyés » ne doit jamais faire échouer un message déjà parti.
        try {
            $sent = collect($this->folders())->firstWhere('role', 'sent');

            if ($sent !== null) {
                $this->folder($sent['path'])->appendMessage($email->toString(), ['\\Seen']);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function inboxState(int $afterUid): array
    {
        $inbox = $this->folder('INBOX');
        $status = $inbox->status();

        $new = [];
        $last = $afterUid;

        if ((int) ($status['uidnext'] ?? 0) - 1 > $afterUid) {
            $messages = $inbox->query()->leaveUnread()->setFetchBody(false)->setFetchFlags(true)->softFail()->getByUidGreater($afterUid);

            foreach ($messages as $message) {
                $summary = $this->summary($message);
                $last = max($last, $summary['uid']);

                if (! $summary['seen']) {
                    $new[] = $summary;
                }
            }
        }

        return ['unseen' => (int) ($status['unseen'] ?? 0), 'last_uid' => $last, 'new' => $new];
    }

    private function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $config = WebmailSettings::get();

        $client = (new ClientManager())->make([
            'host' => $config['imap_host'],
            'port' => $config['imap_port'],
            'encryption' => $config['imap_port'] === 143 ? 'starttls' : 'ssl',
            'validate_cert' => true,
            'username' => $this->mailbox->address,
            'password' => (string) $this->mailbox->password,
            'protocol' => 'imap',
            'timeout' => 20,
        ]);

        try {
            $client->connect();
        } catch (\Throwable $exception) {
            throw new WebmailException('Connexion à la boîte impossible : '.Str::limit(strip_tags($this->scrub($exception->getMessage())), 160));
        }

        return $this->client = $client;
    }

    private function folder(string $path): Folder
    {
        if (isset($this->folderCache[$path])) {
            return $this->folderCache[$path];
        }

        $folder = $this->client()->getFolderByPath($path, false, true) ?? $this->client()->getFolder($path);

        if ($folder === null) {
            throw new WebmailException('Dossier introuvable.');
        }

        return $this->folderCache[$path] = $folder;
    }

    private function fetch(string $folder, int $uid, bool $withBody = true): ?Message
    {
        try {
            $query = $this->folder($folder)->query()->leaveUnread()->softFail()->setFetchBody($withBody)->setFetchFlags(true);

            return $query->getMessageByUid($uid);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function summary(Message $message): array
    {
        $date = null;

        try {
            $date = $message->get('date')->toDate()->toIso8601String();
        } catch (\Throwable) {
        }

        $from = $this->addresses($message->get('from'))[0] ?? ['name' => '', 'email' => ''];
        $type = strtolower((string) $message->getHeader()?->get('content_type'));

        return [
            'uid' => (int) $message->getUid(),
            'subject' => trim((string) $message->get('subject')) ?: '(sans objet)',
            'from' => $from,
            'to' => $this->addresses($message->get('to')),
            'cc' => $this->addresses($message->get('cc')),
            'date' => $date,
            'seen' => $message->hasFlag('Seen'),
            'flagged' => $message->hasFlag('Flagged'),
            'answered' => $message->hasFlag('Answered'),
            'attachments' => str_contains($type, 'multipart/mixed') ? 1 : 0,
        ];
    }

    /** @return array<int, array{name: string, email: string}> */
    private function addresses(mixed $attribute): array
    {
        $rows = [];

        foreach ($attribute?->all() ?? [] as $address) {
            $email = strtolower(trim((string) ($address->mail ?? '')));

            if ($email !== '') {
                $rows[] = ['name' => trim((string) ($address->personal ?? '')), 'email' => $email];
            }
        }

        return $rows;
    }

    private function email(array $message, bool $draft = false): Email
    {
        $from = new Address($this->mailbox->address, $this->mailbox->title());
        $email = (new Email())->from($from)->subject((string) ($message['subject'] ?? ''));

        foreach (['to' => 'addTo', 'cc' => 'addCc', 'bcc' => 'addBcc'] as $field => $method) {
            foreach ((array) ($message[$field] ?? []) as $address) {
                $email->{$method}(Address::create($address));
            }
        }

        $text = (string) ($message['body'] ?? '');
        $html = '<div style="white-space:pre-wrap;font-family:Arial,sans-serif;font-size:14px">'.e($text === '' ? ' ' : $text).'</div>';
        $signature = trim((string) $this->mailbox->signature);

        if ($signature !== '' && ! $draft) {
            $text = rtrim($text)."\n\n-- \n".WebmailSignature::text($signature);
            $html .= '<div style="margin-top:16px;font-family:Arial,sans-serif;font-size:14px">-- <br>'.WebmailSignature::html($signature).'</div>';
        }

        $email->text($text === '' ? ' ' : $text)->html($html);

        if (! empty($message['in_reply_to'])) {
            $email->getHeaders()->addIdHeader('In-Reply-To', trim((string) $message['in_reply_to'], '<> '));
            $references = trim(($message['references'] ?? '').' '.$message['in_reply_to']);
            $ids = array_values(array_filter(array_map(fn (string $id): string => trim($id, '<> '), preg_split('/\s+/', $references) ?: [])));

            if ($ids !== []) {
                $email->getHeaders()->addIdHeader('References', $ids);
            }
        }

        foreach ((array) ($message['attachments'] ?? []) as $file) {
            $email->attachFromPath($file['path'], $file['name'], $file['mime'] ?: null);
        }

        return $email;
    }

    private function displayName(Folder $folder): string
    {
        return $folder->name !== '' ? (string) $folder->name : (string) $folder->full_name;
    }

    /** Aucun mot de passe dans les messages d'erreur. */
    private function scrub(string $text): string
    {
        $password = (string) $this->mailbox->password;

        return $password !== '' ? str_replace([$password, rawurlencode($password)], '***', $text) : $text;
    }
}
