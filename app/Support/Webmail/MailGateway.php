<?php

namespace App\Support\Webmail;

/**
 * Ce que le webmail demande à un serveur de messagerie. L'implémentation réelle parle IMAP / SMTP ({@see ImapGateway}) ;
 * les tests la remplacent par une boîte en mémoire.
 *
 * Un message est un tableau : uid, subject, from [name, email], to [[name, email]], cc, date (ISO 8601), seen, flagged,
 * answered, attachments (nombre ou détail), et pour la lecture : html, text, message_id, references.
 * Un message à envoyer : to[], cc[], bcc[], subject, body, in_reply_to, references, attachments [[path, name, mime]].
 */
interface MailGateway
{
    /** @return array<int, array{path: string, name: string, role: string, unseen: int, total: int}> */
    public function folders(): array;

    /**
     * @param array<int, int>|null $uids Se limiter à ces messages (étiquettes).
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function messages(string $folder, int $page, int $perPage, ?string $search = null, ?array $uids = null): array;

    /** @return array<string, mixed>|null */
    public function message(string $folder, int $uid): ?array;

    /** @return array{name: string, mime: string, content: string}|null */
    public function attachment(string $folder, int $uid, int $index): ?array;

    /** @param string $flag seen | flagged | answered */
    public function mark(string $folder, int $uid, string $flag, bool $on): void;

    public function move(string $folder, int $uid, string $target): void;

    /** Corbeille ; suppression définitive si le message y est déjà. */
    public function trash(string $folder, int $uid): void;

    /** Enregistre le brouillon dans « Brouillons » et remplace l'ancienne version. */
    public function saveDraft(array $message, ?int $replaceUid = null): void;

    /** Envoie par SMTP puis range une copie dans « Envoyés ». */
    public function send(array $message): void;

    /**
     * Nouveaux messages de la réception depuis le dernier UID connu.
     *
     * @return array{unseen: int, last_uid: int, new: array<int, array<string, mixed>>}
     */
    public function inboxState(int $afterUid): array;

    /** Teste la connexion IMAP (identifiants). */
    public function ping(): void;
}
