<?php

namespace App\Support\Academy\Payments;

use App\Models\AcademyOrder;

/**
 * Paiement hors ligne (virement, espèces) : la commande reste en attente jusqu'à validation par l'administration.
 */
final class ManualGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Paiement manuel';
    }

    public function description(): string
    {
        return 'Le membre paie hors ligne ; l’administration valide la commande une fois le paiement reçu.';
    }

    public function start(AcademyOrder $order): string
    {
        return route('academy.orders.show', $order);
    }
}
