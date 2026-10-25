<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reparation : la migration des mandats avait cree une racine vide pour le mandat en cours,
 * ce qui masquait l'organigramme existant. Aucune donnee n'est supprimee de maniere destructive :
 * une racine auto-creee vide est retiree, sinon elle est rattachee sous la racine historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        $current = DB::table('mandates')->where('is_current', true)->first()
            ?? DB::table('mandates')->orderByDesc('start_year')->first();

        if (! $current) {
            return;
        }

        $mandateRoots = DB::table('mandates')->whereNotNull('root_node_id')->pluck('root_node_id')->all();

        $legacyRootId = DB::table('org_nodes')
            ->whereNull('parent_id')
            ->whereNotIn('id', $mandateRoots)
            ->orderBy('id')
            ->value('id');

        if (! $legacyRootId) {
            return;
        }

        $autoRootId = $current->root_node_id;

        DB::table('mandates')->where('id', $current->id)->update(['root_node_id' => $legacyRootId]);

        if (! $autoRootId || (int) $autoRootId === (int) $legacyRootId) {
            return;
        }

        $hasChildren = DB::table('org_nodes')->where('parent_id', $autoRootId)->exists();
        $hasPeople = DB::table('org_assignments')->where('node_id', $autoRootId)->exists();

        if (! $hasChildren && ! $hasPeople) {
            DB::table('org_nodes')->where('id', $autoRootId)->delete();

            return;
        }

        // Du contenu a deja ete ajoute sous la racine auto-creee : on le conserve sous la racine historique.
        DB::table('org_nodes')->where('id', $autoRootId)->update(['parent_id' => $legacyRootId]);
    }

    public function down(): void
    {
        // Reparation de donnees : pas de retour arriere.
    }
};
