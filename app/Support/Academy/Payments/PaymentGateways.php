<?php

namespace App\Support\Academy\Payments;

use App\Support\Academy\AcademySettings;

/** Registre des prestataires de paiement disponibles. */
final class PaymentGateways
{
    /**
     * @return array<string, PaymentGateway>
     */
    public static function all(): array
    {
        $gateways = [new ManualGateway()];

        return collect($gateways)->keyBy(fn (PaymentGateway $gateway): string => $gateway->key())->all();
    }

    /** Prestataire choisi dans les réglages (le paiement manuel à défaut). */
    public static function active(): PaymentGateway
    {
        $all = self::all();

        return $all[AcademySettings::get('gateway')] ?? $all['manual'];
    }

    public static function find(string $key): PaymentGateway
    {
        return self::all()[$key] ?? self::all()['manual'];
    }
}
