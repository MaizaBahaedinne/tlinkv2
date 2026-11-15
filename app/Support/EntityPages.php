<?php

namespace App\Support;

use App\Models\MarketingAccount;
use App\Models\OrgNode;
use Illuminate\Support\Facades\Schema;

/**
 * Pages Facebook et Instagram des entités. Le coffre (comptes marketing) est la source : les liens saisis ici sont
 * recopiés sur la fiche du club (facebook_url, instagram_url) et sur les réglages du site pour la Fondation, ce qui
 * les affiche partout où la fiche est lue. Inversement, un lien saisi sur la fiche d'un club crée ou met à jour sa page dans le coffre.
 */
final class EntityPages
{
    private const COLUMNS = ['facebook' => 'facebook_url', 'instagram' => 'instagram_url'];

    /**
     * Liens des pages d'une entité (clé = plateforme, valeur = URL http(s) ou null).
     *
     * @return array{facebook: string|null, instagram: string|null}
     */
    public static function urls(?int $clubId, ?string $scope): array
    {
        $urls = ['facebook' => null, 'instagram' => null];

        if (! Schema::hasTable('marketing_accounts')) {
            return $urls;
        }

        $accounts = MarketingAccount::query()
            ->whereIn('platform', array_keys(self::COLUMNS))
            ->whereNotNull('profile_url')
            ->when($clubId !== null, fn ($query) => $query->where('club_id', $clubId))
            ->when($clubId === null, fn ($query) => $query->whereNull('club_id')->where('scope', $scope ?: 'foundation'))
            ->orderBy('id')
            ->get(['platform', 'profile_url']);

        foreach ($accounts as $account) {
            if ($urls[$account->platform] === null && self::valid($account->profile_url)) {
                $urls[$account->platform] = $account->profile_url;
            }
        }

        return $urls;
    }

    /**
     * Liens affichés sur la page d'une entité : [libellé, icône, URL] pour chaque page renseignée.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public static function links(?int $clubId, ?string $scope): array
    {
        $urls = self::urls($clubId, $scope);
        $links = [];

        if ($urls['facebook'] !== null) {
            $links[] = ['Facebook', 'fa-brands fa-facebook', $urls['facebook']];
        }

        if ($urls['instagram'] !== null) {
            $links[] = ['Instagram', 'fa-brands fa-instagram', $urls['instagram']];
        }

        return $links;
    }

    /** Recopie les liens du coffre vers la fiche du club ou les réglages du site (sans déclencher d'événement). */
    public static function push(MarketingAccount $account): void
    {
        if (! array_key_exists($account->platform, self::COLUMNS)) {
            return;
        }

        $urls = self::urls($account->club_id, $account->scope);

        if ($account->club_id !== null) {
            OrgNode::query()->whereKey($account->club_id)->update(['facebook_url' => $urls['facebook'], 'instagram_url' => $urls['instagram']]);

            return;
        }

        if (($account->scope ?: 'foundation') === 'foundation') {
            $settings = new SiteSettings();
            $stored = $settings->raw();

            foreach (self::COLUMNS as $platform => $key) {
                if ($urls[$platform] !== null) {
                    $stored[$key] = $urls[$platform];
                } elseif (($stored[$key] ?? '') === ($account->getOriginal('profile_url') ?? '')) {
                    unset($stored[$key]);
                }
            }

            $settings->save($stored);
        }
    }

    /** Un lien saisi sur la fiche d'un club devient sa page dans le coffre. */
    public static function pull(OrgNode $club): void
    {
        if ($club->kind !== 'club' || ! Schema::hasTable('marketing_accounts')) {
            return;
        }

        foreach (self::COLUMNS as $platform => $column) {
            if (! $club->wasChanged($column)) {
                continue;
            }

            $url = self::valid($club->{$column}) ? $club->{$column} : null;
            $account = MarketingAccount::query()->where('club_id', $club->id)->where('platform', $platform)->orderBy('id')->first();

            if ($account !== null) {
                if ($account->profile_url !== $url) {
                    $account->profile_url = $url;
                    $account->saveQuietly();
                }

                continue;
            }

            if ($url !== null) {
                MarketingAccount::query()->create([
                    'club_id' => $club->id,
                    'platform' => $platform,
                    'name' => MarketingAccount::PLATFORMS[$platform].' — '.$club->title,
                    'profile_url' => $url,
                    'two_factor' => 'none',
                ]);
            }
        }
    }

    public static function valid(?string $url): bool
    {
        return $url !== null && preg_match('#^https?://\S+$#i', $url) === 1;
    }
}
