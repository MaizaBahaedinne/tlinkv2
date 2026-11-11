<?php

namespace App\Support\Cards;

use App\Models\MemberCard;

/**
 * Contenu du QR code d'une carte : numéro + pas de temps + signature (HMAC avec la clé propre à la carte).
 * Le code change toutes les 30 secondes : une capture d'écran ne sert que quelques instants.
 * Format : TLC1.<numéro>.<pas>.<signature>
 */
final class CardCode
{
    public const PREFIX = 'TLC1';

    /** Durée d'un pas, en secondes. */
    public const STEP = 30;

    /** Pas tolérés avant et après le pas courant (décalage d'horloge, lenteur de scan) : ~1 min 30 de validité. */
    public const TOLERANCE = 2;

    public static function payload(MemberCard $card, ?int $time = null): string
    {
        $step = intdiv($time ?? time(), self::STEP);

        return implode('.', [self::PREFIX, $card->number, $step, self::signature($card, $step)]);
    }

    /**
     * @return array{0: MemberCard|null, 1: string|null} carte retrouvée, ou motif du refus
     */
    public static function resolve(string $payload, ?int $time = null): array
    {
        if (! preg_match('/^'.self::PREFIX.'\.(TL-[A-Z2-9]{4}-[A-Z2-9]{4})\.(\d{1,10})\.([A-Za-z0-9_-]{16})$/', trim($payload), $parts)) {
            return [null, 'Ce QR code n’est pas une carte de membre T.Link.'];
        }

        $card = MemberCard::query()->with(['user:id,name', 'membership.node:id,title', 'mandate:id,label'])->where('number', $parts[1])->first();

        if ($card === null || ! hash_equals(self::signature($card, (int) $parts[2]), $parts[3])) {
            return [null, 'Carte inconnue ou QR code falsifié.'];
        }

        if (abs(intdiv($time ?? time(), self::STEP) - (int) $parts[2]) > self::TOLERANCE) {
            return [null, 'QR code périmé : le membre doit rouvrir sa carte pour l’actualiser.'];
        }

        return [$card, null];
    }

    private static function signature(MemberCard $card, int $step): string
    {
        $mac = hash_hmac('sha256', $card->number.'.'.$step, (string) $card->secret, true);

        return substr(rtrim(strtr(base64_encode($mac), '+/', '-_'), '='), 0, 16);
    }
}
