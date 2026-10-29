<?php

namespace App\Support\Academy;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        // Certificat : logo du projet, signataires (nom, fonction, image de signature) — fichiers sur le disque public.
        'logo_path' => '',
        'manager_name' => '',
        'manager_title' => 'Chef de projet Tunivisions Academy',
        'manager_signature_path' => '',
        'president_name' => '',
        'president_title' => 'Président de la Fondation Tunivisions',
        'president_signature_path' => '',
    ];

    /** Dossier (disque public) des images du certificat. */
    public const FILES_DIR = 'academy/certificate';

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $stored = [];

        if (Schema::hasTable('settings')) {
            $decoded = json_decode((string) Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->value('value'), true);
            $stored = is_array($decoded) ? $decoded : [];
        }

        $values = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = $stored[$key] ?? $default;
            $values[$key] = is_bool($default) ? (bool) $value : (string) $value;
        }

        return $values;
    }

    /** URL publique d'une image du certificat (logo, signature), ou null si elle n'est pas renseignée. */
    public static function fileUrl(string $key): ?string
    {
        $path = (string) self::get($key);

        return str_starts_with($path, self::FILES_DIR.'/') ? Storage::disk('public')->url($path) : null;
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
