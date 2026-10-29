<?php

namespace App\Support\Academy\Payments;

use App\Models\AcademyOrder;

/**
 * Prestataire de paiement. Pour brancher un paiement en ligne (Konnect, Paymee, Flouci…) :
 * créer une classe qui l'implémente, l'ajouter à PaymentGateways::all(), puis — au retour du prestataire —
 * appeler AcademyOrders::markPaid($order). Aucun autre code Academy n'est à modifier.
 */
interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /**
     * Démarre le paiement d'une commande en attente et renvoie l'adresse où envoyer le membre
     * (page du prestataire, ou page d'instructions pour un paiement hors ligne).
     */
    public function start(AcademyOrder $order): string;
}
