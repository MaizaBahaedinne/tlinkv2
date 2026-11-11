<?php

namespace App\Support\Cards;

use App\Models\CardScan;
use App\Models\MemberCard;
use App\Models\User;
use RuntimeException;

/** Enregistre un pointage (entrée ou sortie) après avoir contrôlé la carte et le droit de l'opérateur sur la cible. */
final class CardScanner
{
    /** Un même pointage répété dans ce délai (double lecture du QR code) n'est enregistré qu'une fois. */
    private const DUPLICATE_SECONDS = 8;

    /**
     * @param string $direction in, out ou auto (alterne entrée / sortie selon le dernier pointage de la carte sur cette cible)
     * @return array{scan: CardScan, duplicate: bool, note: string|null}
     *
     * @throws RuntimeException message affichable à l'opérateur
     */
    public static function record(MemberCard $card, string $type, int $subjectId, string $direction, string $method, User $operator): array
    {
        if ($card->status === MemberCard::STATUS_SUSPENDED) {
            throw new RuntimeException('Carte suspendue : le pointage est refusé.');
        }

        if (! $card->isValid()) {
            throw new RuntimeException('Carte expirée : l’adhésion n’est plus active pour le mandat en cours.');
        }

        $target = Checkpoints::find($type, $subjectId, $operator);

        if ($target === null) {
            throw new RuntimeException('Cette cible est introuvable ou hors de votre périmètre.');
        }

        $last = CardScan::query()->where('member_card_id', $card->id)->where('subject_type', $type)->where('subject_id', $subjectId)
            ->latest('scanned_at')->latest('id')->first();

        $note = Checkpoints::note($type, $subjectId, $card->user);

        if ($last !== null && $last->scanned_at->gt(now()->subSeconds(self::DUPLICATE_SECONDS)) && ($direction === 'auto' || $last->direction === $direction)) {
            return ['scan' => $last, 'duplicate' => true, 'note' => $note];
        }

        $resolved = $direction === 'auto' ? ($last?->direction === 'in' ? 'out' : 'in') : $direction;

        $scan = CardScan::query()->create([
            'member_card_id' => $card->id,
            'user_id' => $card->user_id,
            'subject_type' => $type,
            'subject_id' => $subjectId,
            'subject_label' => $target['label'],
            'scope' => $target['scope'],
            'node_id' => $target['node_id'],
            'direction' => $resolved,
            'method' => $method,
            'scanned_by' => $operator->id,
            'scanned_at' => now(),
        ]);

        return ['scan' => $scan, 'duplicate' => false, 'note' => $note];
    }
}
