<?php

namespace App\Support\Cards;

use App\Models\Mandate;
use App\Models\MemberCard;
use App\Models\Membership;
use Illuminate\Support\Facades\Schema;

/**
 * Cycle de vie des cartes de membre : une carte par adhésion validée et par mandat.
 * Au changement de mandat, chaque adhésion encore active reçoit sa nouvelle carte ; les autres cartes expirent.
 */
final class MemberCards
{
    private static bool $ready = false;

    public static function currentMandate(): ?Mandate
    {
        return Mandate::query()->where('status', Mandate::STATUS_CURRENT)->orderByDesc('start_year')->first();
    }

    /**
     * Émet, renouvelle et fait expirer les cartes (de tout le monde, ou d'un seul compte).
     * Sans mandat en cours, rien ne change : seule la date d'échéance de chaque carte s'applique.
     *
     * @return array{issued: int, expired: int}
     */
    public static function sync(?int $userId = null): array
    {
        $result = ['issued' => 0, 'expired' => 0];

        if (! self::ready() || ($mandate = self::currentMandate()) === null) {
            return $result;
        }

        $keep = [];

        Membership::query()
            ->where('status', 'approved')
            ->duringMandate($mandate)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->chunkById(500, function ($memberships) use ($mandate, &$keep, &$result): void {
                $cards = MemberCard::query()->where('mandate_id', $mandate->id)
                    ->whereIn('membership_id', $memberships->pluck('id'))->get()->keyBy('membership_id');

                foreach ($memberships as $membership) {
                    $expiresAt = $mandate->windowEnd();

                    if ($membership->ends_at !== null && $membership->ends_at->copy()->endOfDay()->lt($expiresAt)) {
                        $expiresAt = $membership->ends_at->copy()->endOfDay();
                    }

                    $card = $cards->get($membership->id);

                    if ($card === null) {
                        $card = MemberCard::query()->create([
                            'user_id' => $membership->user_id,
                            'membership_id' => $membership->id,
                            'mandate_id' => $mandate->id,
                            'number' => MemberCard::newNumber(),
                            'secret' => MemberCard::newSecret(),
                            'status' => MemberCard::STATUS_ACTIVE,
                            'issued_at' => now(),
                            'expires_at' => $expiresAt,
                        ]);
                        $result['issued']++;
                    } else {
                        // Une carte expirée dont l'adhésion redevient active est réactivée ; une carte suspendue le reste.
                        if ($card->status === MemberCard::STATUS_EXPIRED) {
                            $card->status = MemberCard::STATUS_ACTIVE;
                        }

                        $card->expires_at = $expiresAt;
                        $card->save();
                    }

                    $keep[] = $card->id;
                }
            });

        $result['expired'] = MemberCard::query()
            ->where('status', MemberCard::STATUS_ACTIVE)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($keep !== [], fn ($query) => $query->whereNotIn('id', $keep))
            ->update(['status' => MemberCard::STATUS_EXPIRED]);

        return $result;
    }

    private static function ready(): bool
    {
        return self::$ready || (self::$ready = Schema::hasTable('member_cards'));
    }
}
