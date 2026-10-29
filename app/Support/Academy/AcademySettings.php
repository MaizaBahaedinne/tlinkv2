<?php

namespace App\Support\Academy;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de Tunivisions Academy, stockés en base (modifiables depuis l'administration, pas dans .env).
 */
final class AcademySettings
{
    public const NAME = 'Tunivisions Academy';

    private const GROUP = 'academy';

    private const KEY = 'config';

    public const DEFAULTS = [
        // Ventes fermées tant que l'administration ne les ouvre pas : seuls les cours gratuits sont accessibles.
        'sales_enabled' => false,
        // Clé du prestataire de paiement (voir PaymentGateways).
        'gateway' => 'manual',
        // Consignes affichées au membre qui paie hors ligne (virement, espèces, etc.).
        'manual_instructions' => "Réglez par virement ou en espèces auprès de l'équipe Tunivisions, en indiquant la référence de votre commande. Votre accès est activé dès que le paiement est validé.",
        'contact_email' => '',
    ];

    /**
     * @return array{sales_enabled: bool, gateway: string, manual_instructions: string, contact_email: string}
     */
    public static function all(): array
    {
        $stored = [];

        if (Schema::hasTable('settings')) {
            $decoded = json_decode((string) Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->value('value'), true);
            $stored = is_array($decoded) ? $decoded : [];
        }

        return [
            'sales_enabled' => (bool) ($stored['sales_enabled'] ?? self::DEFAULTS['sales_enabled']),
            'gateway' => (string) ($stored['gateway'] ?? self::DEFAULTS['gateway']),
            'manual_instructions' => (string) ($stored['manual_instructions'] ?? self::DEFAULTS['manual_instructions']),
            'contact_email' => (string) ($stored['contact_email'] ?? self::DEFAULTS['contact_email']),
        ];
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values): void
    {
        $merged = array_merge(self::all(), array_intersect_key($values, self::DEFAULTS));

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode($merged, JSON_UNESCAPED_UNICODE)],
        );
    }
}
