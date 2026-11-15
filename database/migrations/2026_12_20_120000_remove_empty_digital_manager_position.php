<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Retire le poste « Digital manager de la Fondation » créé automatiquement par la migration précédente :
     * les droits de validation s'accordent sur les postes de l'organigramme choisis par l'administration.
     * Le poste n'est supprimé que s'il est vide (aucune affectation, aucun sous-bloc) ; sinon il reste en place.
     */
    public function up(): void
    {
        // Libellés plus explicites : « gestion des demandes » (marketing.review) se distingue du simple dépôt de demande (marketing.requests).
        DB::table('app_modules')->where('key', 'marketing.requests')->update(['name' => 'Dépôt d’une demande d’accès aux réseaux']);
        DB::table('app_modules')->where('key', 'marketing.review')->update(['name' => 'Gestion des demandes d’accès (validation, charte, portfolio Meta)']);

        $node = DB::table('org_nodes')->where('system_key', 'digital-manager')->first();

        if ($node === null) {
            return;
        }

        $busy = DB::table('org_assignments')->where('node_id', $node->id)->exists()
            || DB::table('org_nodes')->where('parent_id', $node->id)->exists();

        if (! $busy) {
            DB::table('org_nodes')->where('id', $node->id)->delete();
        }
    }

    public function down(): void
    {
        // Le poste n'est pas recréé.
    }
};
