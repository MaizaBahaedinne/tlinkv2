<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Code de confirmation de l'adresse e-mail : 6 chiffres envoyés par e-mail, valables 15 minutes.
 *
 * Le code n'est jamais stocké en clair (empreinte liée au compte et à l'adresse), un nouvel envoi invalide le précédent
 * et le code est détruit après trop d'essais ratés.
 */
final class EmailVerificationCodes
{
    public const LENGTH = 6;

    public const VALID_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const OK = 'ok';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    public const LOCKED = 'locked';

    /** Génère un nouveau code (l'ancien devient inutilisable) et le renvoie en clair pour l'e-mail. */
    public static function issue(User $user): string
    {
        $code = str_pad((string) random_int(0, (10 ** self::LENGTH) - 1), self::LENGTH, '0', STR_PAD_LEFT);

        Cache::put(self::key($user), ['hash' => self::hash($user, $code), 'attempts' => 0, 'expires_at' => now()->addMinutes(self::VALID_MINUTES)->getTimestamp()], now()->addMinutes(self::VALID_MINUTES));

        return $code;
    }

    /**
     * Contrôle le code saisi. Un code juste est consommé ; un code faux compte un essai.
     *
     * @return self::OK|self::INVALID|self::EXPIRED|self::LOCKED
     */
    public static function check(User $user, string $input): string
    {
        $entry = Cache::get(self::key($user));

        if (! is_array($entry) || (int) ($entry['expires_at'] ?? 0) <= now()->getTimestamp()) {
            Cache::forget(self::key($user));

            return self::EXPIRED;
        }

        $input = preg_replace('/\s+/', '', $input) ?? '';

        if (hash_equals((string) $entry['hash'], self::hash($user, $input))) {
            Cache::forget(self::key($user));

            return self::OK;
        }

        $attempts = (int) $entry['attempts'] + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget(self::key($user));

            return self::LOCKED;
        }

        // L'échéance reste celle de l'envoi : un essai raté ne prolonge jamais la validité du code.
        Cache::put(self::key($user), ['hash' => $entry['hash'], 'attempts' => $attempts, 'expires_at' => $entry['expires_at']], now()->addMinutes(self::VALID_MINUTES));

        return self::INVALID;
    }

    /** Oublie le code en cours (adresse changée, compte confirmé). */
    public static function forget(User $user): void
    {
        Cache::forget(self::key($user));
    }

    private static function key(User $user): string
    {
        return 'email-verification-code:'.$user->id;
    }

    /** Lié à l'adresse : un code reçu sur l'ancienne adresse ne valide pas la nouvelle. */
    private static function hash(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.mb_strtolower((string) $user->email).'|'.$code, (string) config('app.key'));
    }
}
