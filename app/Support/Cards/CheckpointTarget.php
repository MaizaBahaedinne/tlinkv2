<?php

namespace App\Support\Cards;

use App\Models\User;

/**
 * Ce sur quoi un membre peut pointer (activité de club, événement, local…). Chaque module de la plateforme
 * qui veut du pointage ajoute sa propre cible dans {@see Checkpoints::targets()} : le scanner, l'historique et
 * la limitation au périmètre de l'opérateur fonctionnent alors sans autre changement.
 */
interface CheckpointTarget
{
    /** Identifiant stable stocké avec chaque pointage (ex. « activity »). */
    public function key(): string;

    public function label(): string;

    /**
     * Cibles que l'opérateur peut pointer en ce moment (bornées à son périmètre).
     *
     * @return array<int, string> identifiant => libellé
     */
    public function options(User $operator): array;

    /**
     * Une cible précise, si l'opérateur a le droit d'y pointer ; null sinon.
     *
     * @return array{label: string, scope: string|null, node_id: int|null}|null
     */
    public function find(User $operator, int $id): ?array;

    /** Information utile à l'opérateur sur ce membre pour cette cible (ex. « Inscrit »). */
    public function note(int $id, User $member): ?string;
}
