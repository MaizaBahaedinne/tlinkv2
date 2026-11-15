<?php

namespace App\Support;

use App\Models\MarketingCharter;

/** Charte d'usage des réseaux sociaux : celle de l'entité si elle existe, sinon la charte commune, sinon le texte intégré. */
final class MarketingCharters
{
    public const DEFAULT_TITLE = 'Charte d’usage des réseaux sociaux — {entite}';

    public const DEFAULT_BODY = <<<'TXT'
En recevant l’accès aux pages Facebook et Instagram de {entite}, je m’engage à respecter la présente charte.

1. Représentation. Je m’exprime au nom de {entite} et de la Fondation Tunivisions : mes publications sont courtoises, exactes et conformes à ses valeurs. Aucun propos haineux, discriminatoire, politique partisan ou religieux prosélyte.

2. Accès personnels et confidentiels. Les mots de passe qui me sont remis sont strictement personnels. Je ne les communique à personne, je ne les enregistre pas dans un endroit partagé et je ne les modifie pas sans l’accord du digital manager de la Fondation.

3. Sécurité. J’active la double authentification sur mon compte personnel, j’utilise un appareil protégé et je me déconnecte des appareils partagés. Je signale immédiatement toute connexion suspecte ou perte d’accès.

4. Contenus. Je ne publie que des contenus dont {entite} détient les droits (images, musiques, logos) et je respecte la vie privée : pas de photo de mineur ou de membre sans son autorisation.

5. Portfolio Meta. L’accès aux pages passe par le portfolio Meta qui m’est attribué par la Fondation : je ne l’utilise que pour {entite} et je n’invite personne d’autre à la place de la Fondation.

6. Fin de mission. Lorsque je quitte mon poste ou à la demande de la Fondation, mon accès est retiré et je restitue tous les accès et contenus de {entite}.

7. Suivi. J’accepte que la Fondation consulte et trace la remise des accès, et qu’elle puisse les retirer en cas de manquement à cette charte.
TXT;

    /**
     * @return array{title: string, body: string, version: int, id: int|null, custom: bool}
     */
    public static function forEntity(?int $clubId, ?string $scope): array
    {
        $entityKey = MarketingEntities::keyOf($clubId, $scope);
        $label = MarketingEntities::label($clubId, $scope);

        $charter = MarketingCharter::query()->where('entity', $entityKey)->first()
            ?? MarketingCharter::query()->where('entity', 'default')->first();

        $title = $charter?->title ?? self::DEFAULT_TITLE;
        $body = $charter?->body ?? self::DEFAULT_BODY;

        return [
            'title' => str_replace('{entite}', $label, $title),
            'body' => str_replace('{entite}', $label, $body),
            'version' => $charter?->version ?? 1,
            'id' => $charter?->id,
            'custom' => $charter !== null,
        ];
    }
}
