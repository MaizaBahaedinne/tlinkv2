<?php

namespace Tests\Support;

use App\Support\Webmail\MailGateway;
use App\Support\Webmail\WebmailException;

/** Boîte en mémoire : remplace IMAP / SMTP dans les tests. */
class FakeMailGateway implements MailGateway
{
    /** @var array<string, array<int, array<string, mixed>>> */
    public array $messages = [];

    public array $sent = [];

    public array $drafts = [];

    public array $marks = [];

    public array $trashed = [];

    public array $moved = [];

    public bool $down = false;

    public int $unseen = 0;

    public function folders(): array
    {
        $this->guard();

        return [
            ['path' => 'INBOX', 'name' => 'INBOX', 'role' => 'inbox', 'unseen' => $this->unseen, 'total' => count($this->messages['INBOX'] ?? [])],
            ['path' => 'Drafts', 'name' => 'Drafts', 'role' => 'drafts', 'unseen' => 0, 'total' => 0],
            ['path' => 'Sent', 'name' => 'Sent', 'role' => 'sent', 'unseen' => 0, 'total' => 0],
            ['path' => 'Trash', 'name' => 'Trash', 'role' => 'trash', 'unseen' => 0, 'total' => 0],
        ];
    }

    public function messages(string $folder, int $page, int $perPage, ?string $search = null, ?array $uids = null): array
    {
        $this->guard();
        $items = collect($this->messages[$folder] ?? [])
            ->when($uids !== null, fn ($items) => $items->whereIn('uid', $uids))
            ->when($search, fn ($items) => $items->filter(fn ($item) => str_contains(strtolower($item['subject']), strtolower($search))));

        return ['items' => $items->values()->all(), 'total' => $items->count()];
    }

    public function message(string $folder, int $uid): ?array
    {
        $this->guard();

        return collect($this->messages[$folder] ?? [])->firstWhere('uid', $uid);
    }

    public function attachment(string $folder, int $uid, int $index): ?array
    {
        return ['name' => 'devis.pdf', 'mime' => 'application/pdf', 'content' => '%PDF-fake'];
    }

    public function mark(string $folder, int $uid, string $flag, bool $on): void
    {
        $this->marks[] = [$folder, $uid, $flag, $on];
    }

    public function move(string $folder, int $uid, string $target): void
    {
        $this->moved[] = [$folder, $uid, $target];
    }

    public function trash(string $folder, int $uid): void
    {
        $this->trashed[] = [$folder, $uid];
    }

    public function saveDraft(array $message, ?int $replaceUid = null): void
    {
        $this->drafts[] = [$message, $replaceUid];
    }

    public function send(array $message): void
    {
        $this->guard();
        $this->sent[] = $message;
    }

    public function inboxState(int $afterUid): array
    {
        $this->guard();
        $all = collect($this->messages['INBOX'] ?? []);
        $new = $all->where('uid', '>', $afterUid)->values()->all();

        return ['unseen' => $this->unseen, 'last_uid' => (int) $all->max('uid'), 'new' => $new];
    }

    public function ping(): void
    {
        $this->guard();
    }

    public static function mail(int $uid, string $subject, string $from = 'jean@example.org', array $extra = []): array
    {
        return $extra + [
            'uid' => $uid, 'subject' => $subject, 'from' => ['name' => 'Jean', 'email' => $from], 'to' => [['name' => '', 'email' => 'contact@fondation.test']], 'cc' => [],
            'date' => '2026-11-10T10:00:00+01:00', 'seen' => false, 'flagged' => false, 'answered' => false, 'attachments' => [],
            'html' => '<p>Bonjour</p><script>alert(1)</script><img src="https://pixel.example/t.gif"><a href="javascript:alert(2)" onclick="x()">lien</a>',
            'text' => 'Bonjour', 'message_id' => '<abc@example.org>', 'references' => '',
        ];
    }

    private function guard(): void
    {
        if ($this->down) {
            throw new WebmailException('Connexion à la boîte impossible.');
        }
    }
}
