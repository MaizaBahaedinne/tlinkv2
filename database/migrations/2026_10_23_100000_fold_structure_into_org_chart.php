<?php

use App\Models\OrgNode;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\OrgChart\OrgChartLinker;
use App\Support\OrgScopes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * L'organigramme devient l'unique base de la structure : les tables entities, departments, institutions
 * et clubs disparaissent.
 *
 * - une entite devient un perimetre (texte : foundation, university, high-school, alumni) ;
 * - un club devient un bloc « club » de l'organigramme, qui porte ses informations ;
 * - les adhesions, publications et evenements memorisent leur perimetre (`scope`) et leur club (`node_id`).
 *
 * Une sauvegarde JSON complete est ecrite dans storage/app/backups avant toute suppression.
 */
return new class extends Migration
{
    private const OLD_TABLES = ['entities', 'departments', 'institutions', 'clubs'];

    private const OLD_COLUMNS = ['entity_id', 'department_id', 'institution_id', 'club_id'];

    /** Tables dont les lignes gardent un perimetre et un club. */
    private const CONTENT_TABLES = ['memberships', 'posts', 'events'];

    public function up(): void
    {
        if (! Schema::hasTable('clubs')) {
            return;
        }

        $this->backup();

        foreach (self::CONTENT_TABLES as $table) {
            if (! Schema::hasColumn($table, 'scope')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->string('scope', 30)->nullable()->index();
                    $blueprint->foreignId('node_id')->nullable()->constrained('org_nodes')->nullOnDelete();
                });
            }
        }

        $this->moveData();

        $this->dropForeignKeys();
        $this->dropIndexes();
        $this->dropColumns();

        Schema::disableForeignKeyConstraints();

        foreach (self::OLD_TABLES as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        throw new RuntimeException('Migration irreversible : restaurez la structure depuis la sauvegarde storage/app/backups/structure-*.json.');
    }

    private function moveData(): void
    {
        $scopes = DB::table('entities')->pluck('slug', 'id')->all();

        // Perimetre des blocs deja relies a une entite (blocs systeme et clubs).
        foreach (DB::table('org_nodes')->whereNotNull('entity_id')->get(['id', 'entity_id']) as $node) {
            if (isset($scopes[$node->entity_id])) {
                DB::table('org_nodes')->where('id', $node->id)->update(['scope' => $scopes[$node->entity_id]]);
            }
        }

        $clubs = DB::table('clubs')->orderBy('id')->get();

        if ($clubs->isNotEmpty()) {
            $installer = app(OrgChartInstaller::class);
            $installer->isInstalled() ? $installer->restructure() : $installer->install();
        }

        $institutions = DB::table('institutions')->get()->keyBy('id');
        $nodeOf = [];

        foreach ($clubs as $club) {
            $scope = $scopes[$club->entity_id] ?? null;
            $institution = $club->institution_id ? ($institutions[$club->institution_id] ?? null) : null;

            $node = DB::table('org_nodes')->where('club_id', $club->id)->first()
                ?? DB::table('org_nodes')->where('kind', 'club')->whereNull('club_id')->whereRaw('lower(title) = ?', [mb_strtolower($club->name)])->first();

            $fields = [
                'scope' => $scope,
                'status' => $club->status ?? 'active',
                'legacy_id' => $club->legacy_id ?? null,
                'email' => $club->email ?? null,
                'facebook_url' => $club->facebook_url ?? null,
                'founded_on' => $club->founded_on ?? null,
                'charter_signed' => (bool) ($club->charter_signed ?? false),
                'institution_name' => $institution?->name,
                'institution_type' => $institution?->institution_type,
            ];

            if ($node) {
                DB::table('org_nodes')->where('id', $node->id)->update($fields + [
                    'governorate_id' => $node->governorate_id ?? $club->governorate_id,
                ]);
                $nodeOf[$club->id] = (int) $node->id;

                continue;
            }

            $created = $this->createClubNode($club, $scope, $fields);

            if ($created !== null) {
                $nodeOf[$club->id] = $created;
            }
        }

        foreach (self::CONTENT_TABLES as $table) {
            DB::table($table)->whereNotNull('entity_id')->orderBy('id')->each(function ($row) use ($table, $scopes): void {
                if (isset($scopes[$row->entity_id])) {
                    DB::table($table)->where('id', $row->id)->update(['scope' => $scopes[$row->entity_id]]);
                }
            });

            foreach ($nodeOf as $clubId => $nodeId) {
                DB::table($table)->where('club_id', $clubId)->update(['node_id' => $nodeId]);
            }
        }

        $unplaced = $clubs->pluck('id')->diff(array_keys($nodeOf))->values()->all();

        if ($unplaced !== []) {
            Log::warning('Clubs sans bloc dans l\'organigramme apres migration (voir la sauvegarde).', ['club_ids' => $unplaced]);
        }
    }

    /**
     * Cree le bloc d'un club qui n'en avait pas : dans la branche de son perimetre, sinon sous le bloc de ce perimetre.
     *
     * @param array<string, mixed> $fields
     */
    private function createClubNode(object $club, ?string $scope, array $fields): ?int
    {
        $linker = app(OrgChartLinker::class);
        $governorate = $club->governorate_id ? \App\Models\Governorate::query()->find($club->governorate_id) : null;
        $category = 'direction';
        $parent = null;

        if ($branch = $linker->branchFor($scope)) {
            [$parentBranch, $category] = $branch;
            $parent = $linker->groupFor($parentBranch, $governorate);
        } else {
            $systemKey = array_search($scope, OrgChartLinker::SYSTEM_SCOPES, true);
            $parent = $systemKey ? OrgNode::query()->where('system_key', $systemKey)->first() : null;
            $parent ??= OrgNode::query()->whereNull('parent_id')->first();
            $category = $parent?->category ?? 'direction';
        }

        if ($parent === null) {
            return null;
        }

        $max = OrgNode::query()->where('parent_id', $parent->id)->max('sort_order');

        $node = OrgNode::query()->create([
            'parent_id' => $parent->id,
            'relation_type' => 'hierarchy',
            'kind' => 'club',
            'category' => $category,
            'title' => $club->name,
            'subtitle' => $governorate?->name,
            'governorate_id' => $club->governorate_id,
            'is_collapsed' => true,
            'sort_order' => $max === null ? 0 : ((int) $max) + 1,
        ] + $fields);

        return $node->id;
    }

    private function dropForeignKeys(): void
    {
        foreach ($this->tables() as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $targets = array_intersect($foreignKey['columns'], self::OLD_COLUMNS);

                if ($targets === [] && ! in_array($foreignKey['foreign_table'], self::OLD_TABLES, true)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($foreignKey): void {
                    $blueprint->dropForeign($foreignKey['name'] ?? $foreignKey['columns']);
                });
            }
        }
    }

    private function dropIndexes(): void
    {
        foreach ($this->tables() as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                if (($index['primary'] ?? false) || array_intersect($index['columns'], self::OLD_COLUMNS) === []) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $blueprint->dropIndex($index['name']);
                });
            }
        }
    }

    private function dropColumns(): void
    {
        foreach ($this->tables() as $table) {
            $columns = array_values(array_filter(self::OLD_COLUMNS, fn (string $column): bool => Schema::hasColumn($table, $column)));

            if ($columns !== []) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($columns));
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function tables(): array
    {
        return array_values(array_filter(
            Schema::getTableListing(),
            fn (string $table): bool => ! in_array($table, self::OLD_TABLES, true)
        ));
    }

    /**
     * Sauvegarde les tables supprimees et les liens des adhesions, publications et evenements.
     */
    private function backup(): void
    {
        $data = ['exported_at' => now()->toIso8601String()];

        foreach (self::OLD_TABLES as $table) {
            $data[$table] = DB::table($table)->get()->all();
        }

        foreach (self::CONTENT_TABLES as $table) {
            $columns = array_values(array_filter(['id', ...self::OLD_COLUMNS], fn (string $column): bool => Schema::hasColumn($table, $column)));
            $data['links'][$table] = DB::table($table)->get($columns)->all();
        }

        File::ensureDirectoryExists(storage_path('app/backups'));
        File::put(storage_path('app/backups/structure-'.now()->format('Ymd-His').'.json'), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
};
