<?php

namespace App\Support\Guides;

use App\Models\Guide;

/**
 * Guides publiés à l'installation pour que la bibliothèque ne soit jamais vide.
 * Les étapes utilisent des icônes animées : l'administration peut y ajouter des captures ou des GIF.
 */
final class StarterGuides
{
    /** Crée les guides de départ, uniquement si la bibliothèque est vide. */
    public static function install(): void
    {
        if (Guide::query()->exists()) {
            return;
        }

        foreach (self::definitions() as $order => $definition) {
            $steps = $definition['steps'];
            unset($definition['steps']);

            $guide = Guide::query()->create($definition + [
                'slug' => Guide::makeSlug($definition['title']),
                'status' => 'published',
                'published_at' => now(),
                'sort_order' => ($order + 1) * 10,
            ]);

            foreach ($steps as $position => $step) {
                $guide->steps()->create($step + ['position' => $position + 1, 'duration' => 8]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private static function definitions(): array
    {
        return [
            [
                'title' => 'Bien démarrer sur T.Link',
                'summary' => 'Repérez-vous en cinq étapes : navigation, recherche, notifications, messages et menu personnel.',
                'category' => 'Prise en main',
                'audience' => 'all',
                'icon' => 'compass',
                'accent' => 'rose',
                'steps' => [
                    ['icon' => 'house', 'title' => 'Le fil, votre page d’accueil', 'body' => 'Le Fil rassemble les publications de la communauté et de vos entités. Réagissez, commentez et partagez directement depuis chaque publication.'],
                    ['icon' => 'compass', 'title' => 'La barre de navigation', 'body' => 'Sur ordinateur en haut de l’écran, sur téléphone en bas : Fil, Mes tâches et, selon les modules ouverts, Activité, Mentorat et Mon bureau. Le bouton « Plus » (···) regroupe les autres applications : Academy, Boutique, Webmail, Administration…', 'notice_type' => 'info', 'notice' => '« Mon bureau » n’apparaît que si vous occupez un poste dans l’organigramme.'],
                    ['icon' => 'magnifying-glass', 'title' => 'Rechercher', 'body' => 'Tapez dans la barre « Rechercher… » en haut de la page : des suggestions s’affichent pendant la saisie. Cliquez sur l’une d’elles pour l’ouvrir directement.'],
                    ['icon' => 'bell', 'title' => 'Notifications et messages', 'body' => 'La cloche rassemble vos notifications (décisions sur vos demandes, mentions, invitations…). La bulle de discussion ouvre T.Link Messenger pour écrire aux autres membres.', 'notice_type' => 'tip', 'notice' => 'Un badge de couleur signale ce que vous n’avez pas encore lu.'],
                    ['icon' => 'user', 'title' => 'Votre menu personnel', 'body' => 'Cliquez sur votre photo : Mon profil, Mes adhésions, Ma carte de membre, Guides d’utilisation, Scanner un QR code et Déconnexion. Les titulaires de poste y trouvent aussi « Parrainage ».'],
                ],
            ],
            [
                'title' => 'Ma carte de membre et son QR code',
                'summary' => 'Ouvrez votre carte, présentez le QR code à l’entrée d’une activité et comprenez les messages qui s’affichent.',
                'category' => 'Mon compte',
                'audience' => 'all',
                'icon' => 'id-card',
                'accent' => 'sky',
                'steps' => [
                    ['icon' => 'user', 'title' => 'Ouvrir votre carte', 'body' => 'Cliquez sur votre photo en haut à droite, puis sur « Ma carte de membre ».'],
                    ['icon' => 'id-card', 'title' => 'Une carte par adhésion et par mandat', 'body' => 'Chaque adhésion validée, et chaque mandat que vous occupez, donne sa propre carte. Faites-les défiler pour choisir celle à présenter.', 'notice_type' => 'info', 'notice' => 'Tant qu’aucune adhésion n’est validée, la page affiche « Aucune carte active ».'],
                    ['icon' => 'qrcode', 'title' => 'Présenter le QR code', 'body' => 'À l’entrée d’une activité ou d’un événement, montrez le QR code à la personne qui contrôle l’accès. Il se renouvelle automatiquement toutes les 30 secondes.', 'notice_type' => 'warning', 'notice' => 'Une capture d’écran devient vite inutile : gardez la carte ouverte et présentez l’écran en direct.'],
                    ['icon' => 'shield-halved', 'title' => 'Si « Connexion perdue » s’affiche', 'body' => 'Le QR code se met à jour grâce à Internet. Vérifiez votre connexion : dès qu’elle revient, le code se rafraîchit seul.'],
                ],
            ],
            [
                'title' => 'Demander une adhésion',
                'summary' => 'Rejoignez une entité ou un club : choisir où adhérer, rédiger sa lettre de motivation et suivre la réponse.',
                'category' => 'Mon compte',
                'audience' => 'all',
                'icon' => 'people-group',
                'accent' => 'emerald',
                'steps' => [
                    ['icon' => 'user', 'title' => 'Ouvrir « Mes adhésions »', 'body' => 'Cliquez sur votre photo, puis sur « Mes adhésions ». Vous y retrouvez toutes vos demandes et leur état.'],
                    ['icon' => 'hand-pointer', 'title' => 'Demander une adhésion', 'body' => 'Cliquez sur « Demander une adhésion », puis choisissez dans la liste « Où souhaitez-vous adhérer ? » l’entité ou le club de votre choix.'],
                    ['icon' => 'pen', 'title' => 'Rédiger la lettre de motivation', 'body' => 'Présentez-vous et expliquez pourquoi vous souhaitez rejoindre la structure. La lettre doit atteindre le nombre minimum de caractères indiqué.', 'notice_type' => 'warning', 'notice' => 'Votre CV doit être complet dans votre profil. Sinon le bouton d’envoi reste grisé et la page vous indique ce qui manque.'],
                    ['icon' => 'paper-plane', 'title' => 'Envoyer et suivre la réponse', 'body' => 'Cliquez sur « Envoyer ma demande ». Vous êtes notifié dès que la demande est traitée ; votre carte de membre est ensuite émise.'],
                ],
            ],
            [
                'title' => 'Mon bureau : mon équipe et mes supérieurs',
                'summary' => 'Consultez votre place dans l’organigramme et affectez des membres aux postes que vous pilotez.',
                'category' => 'Responsables',
                'audience' => 'holders',
                'icon' => 'briefcase',
                'accent' => 'violet',
                'steps' => [
                    ['icon' => 'briefcase', 'title' => 'Ouvrir « Mon bureau »', 'body' => 'Cet onglet de la navigation est réservé aux titulaires d’un poste. Il présente votre poste et les postes qui l’entourent.'],
                    ['icon' => 'sitemap', 'title' => 'Lire l’organigramme', 'body' => 'Vos supérieurs apparaissent au-dessus, votre poste au centre, puis votre équipe regroupée par niveau : « Niveau −1 », « Niveau −2 »…'],
                    ['icon' => 'users', 'title' => 'Affecter un membre', 'body' => 'Sur un poste de votre équipe, cliquez sur « Affecter un membre », recherchez la personne par son nom puis cliquez sur « Affecter ».'],
                    ['icon' => 'circle-check', 'title' => 'Retirer un membre', 'body' => 'Le bouton « Retirer » met fin à l’affectation de la personne sur ce poste. Elle est informée par notification.', 'notice_type' => 'info', 'notice' => 'Les postes dont les droits sont supérieurs aux vôtres affichent « gestion réservée à l’administration ».'],
                ],
            ],
            [
                'title' => 'Parrainer des personnes vers un poste',
                'summary' => 'Invitez de nouvelles personnes à rejoindre votre équipe par lien, QR code, WhatsApp ou e-mail.',
                'category' => 'Responsables',
                'audience' => 'holders',
                'icon' => 'share-nodes',
                'accent' => 'amber',
                'steps' => [
                    ['icon' => 'user', 'title' => 'Ouvrir « Parrainage »', 'body' => 'Dans le menu de votre photo, cliquez sur « Parrainage ». Cette entrée n’existe que pour les titulaires d’un poste.'],
                    ['icon' => 'sitemap', 'title' => 'Choisir le poste', 'body' => 'L’arbre horizontal montre les postes que vous pouvez pourvoir. Cliquez sur un poste pour afficher son panneau de partage ; le filtre « postes ouverts » masque les postes déjà occupés.'],
                    ['icon' => 'share-nodes', 'title' => 'Partager l’invitation', 'body' => 'Copiez le lien, montrez le QR code, ou envoyez l’invitation par WhatsApp ou par e-mail depuis le panneau.'],
                    ['icon' => 'clipboard-check', 'title' => 'Suivre les candidatures', 'body' => 'La personne invitée dépose sa candidature ; votre équipe l’examine avant de décider.', 'notice_type' => 'tip', 'notice' => 'Un lien est propre à un poste : partagez celui qui correspond à la mission proposée.'],
                ],
            ],
            [
                'title' => 'Le Webmail T.Link',
                'summary' => 'Lire, écrire, classer et retrouver vos e-mails sans quitter la plateforme.',
                'category' => 'Applications',
                'audience' => 'all',
                'icon' => 'envelope',
                'accent' => 'sky',
                'steps' => [
                    ['icon' => 'envelope', 'title' => 'Ouvrir le Webmail', 'body' => 'Le Webmail se trouve dans le menu « Plus » (···). Les dossiers sont listés dans la colonne de gauche.', 'notice_type' => 'info', 'notice' => 'Il n’apparaît que si une boîte e-mail de votre entité vous a été attribuée par l’administration.'],
                    ['icon' => 'magnifying-glass', 'title' => 'Lire, rechercher, mettre en favori', 'body' => 'Cliquez sur un message pour l’ouvrir. La barre de recherche retrouve un message, et l’étoile met en avant ceux qui comptent.'],
                    ['icon' => 'pen', 'title' => 'Écrire un message', 'body' => 'Cliquez sur « Écrire », renseignez le destinataire, l’objet et le message puis envoyez. Dans un message reçu : Répondre, Répondre à tous, Transférer ou Supprimer.'],
                    ['icon' => 'gear', 'title' => 'Ranger et personnaliser', 'body' => 'Déplacez un message vers un dossier, ajoutez des étiquettes de couleur, ou ouvrez les Réglages pour la signature, les étiquettes et les contacts.', 'notice_type' => 'tip', 'notice' => 'Une signature automatique évite de retaper vos coordonnées à chaque message.'],
                ],
            ],
        ];
    }
}
