<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mandates', function (Blueprint $table): void {
            $table->foreignId('root_node_id')->nullable()->after('bureau_name')->constrained('org_nodes')->nullOnDelete();
        });

        $mandates = DB::table('mandates')->whereNull('root_node_id')->orderBy('start_year')->get();

        // Le mandat en cours reprend l'organigramme existant (racine historique) : rien n'est masque.
        $currentId = DB::table('mandates')->where('is_current', true)->value('id')
            ?? DB::table('mandates')->orderByDesc('start_year')->value('id');
        $legacyRootId = DB::table('org_nodes')->whereNull('parent_id')->orderBy('id')->value('id');

        foreach ($mandates as $mandate) {
            if ($legacyRootId && (int) $mandate->id === (int) $currentId) {
                DB::table('mandates')->where('id', $mandate->id)->update(['root_node_id' => $legacyRootId]);

                continue;
            }

            $rootId = DB::table('org_nodes')->insertGetId([
                'parent_id' => null,
                'relation_type' => 'hierarchy',
                'kind' => 'unit',
                'capacity' => 'single',
                'access_level' => 'none',
                'category' => 'direction',
                'title' => $mandate->bureau_name,
                'subtitle' => 'Mandat '.$mandate->label,
                'description' => 'Organigramme du mandat '.$mandate->label,
                'scope' => 'foundation',
                'validation_status' => 'confirmed',
                'children_layout' => 'horizontal',
                'is_collapsed' => false,
                'pos_dx' => 0,
                'pos_dy' => 0,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('mandates')->where('id', $mandate->id)->update(['root_node_id' => $rootId]);
        }
    }

    public function down(): void
    {
        Schema::table('mandates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('root_node_id');
        });
    }
};
