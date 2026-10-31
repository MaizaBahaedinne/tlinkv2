<?php

namespace App\Support\Store;

use App\Models\Setting;
use App\Support\ModuleCatalog;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages de Tunivisions Stores, stockés en base (modifiables depuis l'administration, pas dans .env).
 */
final class StoreSettings
{
    public const MODULE = 'store.catalog';

    private const GROUP = 'store';

    private const KEY = 'config';

    public const DEFAULTS = [
        // Boutique fermée tant que l'administration ne l'ouvre pas.
        'enabled' => false,
        'name' => 'Tunivisions Stores',
        'tagline' => 'Les articles officiels de la Fondation Tunivisions.',
        // Délai (en jours après la livraison) pendant lequel un retour peut être demandé.
        'return_days' => '14',
        'contact_email' => '',
        'contact_phone' => '',
        'return_policy' => '',
    ];

    private static ?bool $ready = null;

    /** La boutique est ouverte : tables migrées, module actif et ouverture décidée dans les réglages. */
    public static function enabled(): bool
    {
        return self::tablesReady() && ModuleCatalog::isActive(self::MODULE) && (bool) self::get('enabled');
    }

    public static function tablesReady(): bool
    {
        return self::$ready ??= Schema::hasTable('store_products');
    }

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

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    /** @param array<string, mixed> $values */
    public static function save(array $values): void
    {
        $merged = array_merge(self::all(), array_intersect_key($values, self::DEFAULTS));

        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode($merged, JSON_UNESCAPED_UNICODE)],
        );
    }

    public static function returnDays(): int
    {
        return max(0, (int) self::get('return_days'));
    }

    public static function money(float $amount, string $currency = 'TND'): string
    {
        return rtrim(rtrim(number_format($amount, 2, ',', ' '), '0'), ',').' '.$currency;
    }
}
