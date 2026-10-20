<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            // single : un seul titulaire ; multiple : plusieurs personnes (assistants, membres...).
            $table->string('capacity', 10)->default('single')->after('kind');
        });

        // Les postes collectifs existants passent en « plusieurs personnes ».
        DB::table('org_nodes')
            ->where('kind', 'position')
            ->where(function ($query): void {
                $query->where('title', 'like', 'Assistant%')
                    ->orWhere('title', 'like', 'Autres membres%')
                    ->orWhere('title', 'like', 'Managers%')
                    ->orWhere('title', 'like', 'Équipe op%');
            })
            ->update(['capacity' => 'multiple']);

        // Un poste qui a deja plusieurs personnes ne doit pas devenir incoherent.
        $crowded = DB::table('org_assignments')
            ->select('node_id')
            ->groupBy('node_id')
            ->havingRaw('count(distinct person_id) > 1')
            ->pluck('node_id');

        if ($crowded->isNotEmpty()) {
            DB::table('org_nodes')->whereIn('id', $crowded)->update(['capacity' => 'multiple']);
        }
    }

    public function down(): void
    {
        Schema::table('org_nodes', function (Blueprint $table): void {
            $table->dropColumn('capacity');
        });
    }
};
