<?php

use App\Models\OrgNode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Les clubs University et High School portent tous le nom « Club Tunivisions » + nom de l'établissement.
     * Mise à jour directe (sans événements du modèle) : seul le titre change.
     */
    public function up(): void
    {
        DB::table('org_nodes')
            ->where('kind', 'club')
            ->whereIn('category', OrgNode::TUNIVISIONS_CLUB_CATEGORIES)
            ->orderBy('id')
            ->get(['id', 'title'])
            ->each(function (object $club): void {
                $title = OrgNode::clubTitle((string) $club->title);

                if ($title !== $club->title) {
                    DB::table('org_nodes')->where('id', $club->id)->update(['title' => $title]);
                }
            });
    }

    public function down(): void
    {
        // Les anciens noms ne sont pas conservés : le préfixe reste.
    }
};
