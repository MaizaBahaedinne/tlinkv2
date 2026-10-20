<?php

use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\OrgChart\OrgChartLinker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Les postes de l'organigramme deviennent les roles de l'application.
 *
 * 1. sauvegarde JSON de toutes les donnees de roles (storage/app/backups) ;
 * 2. transfert des attributions actives vers des postes (sans jamais elever les droits) ;
 * 3. suppression des tables roles, permissions, role_permissions et role_assignments.
 *
 * Les attributions qui n'ont pas de poste equivalent evident ne sont PAS devinees : elles sont listees
 * dans la sauvegarde (cle `unmigrated`) et dans le journal pour etre replacees a la main.
 */
return new class extends Migration
{
    /** Role => poste (cle systeme) qui reprend ses droits ; l'entite du role decide pour les administrateurs d'entite. */
    private const ENTITY_OFFICES = ['university' => 'university-office', 'high-school' => 'highschool-office', 'alumni' => 'alumni'];

    public function up(): void
    {
        if (! Schema::hasTable('role_assignments')) {
            return;
        }

        $today = now()->toDateString();

        $active = DB::table('role_assignments')
            ->join('roles', 'roles.id', '=', 'role_assignments.role_id')
            ->leftJoin('entities', 'entities.id', '=', 'role_assignments.entity_id')
            ->where('role_assignments.status', 'active')
            ->where(fn ($query) => $query->whereNull('role_assignments.ends_at')->orWhere('role_assignments.ends_at', '>=', $today))
            ->orderBy('role_assignments.id')
            ->get(['role_assignments.id', 'role_assignments.user_id', 'role_assignments.entity_id', 'roles.slug as role', 'entities.slug as entity']);

        $unmigrated = [];

        if ($active->isNotEmpty()) {
            $installer = app(OrgChartInstaller::class);

            $installer->isInstalled() ? $installer->restructure() : $installer->install();
            app(OrgChartLinker::class)->sync();
            $installer->applyDefaultAccess();

            foreach ($active as $assignment) {
                if (! $this->transfer($assignment)) {
                    $unmigrated[] = ['user_id' => $assignment->user_id, 'role' => $assignment->role, 'entity' => $assignment->entity];
                }
            }

            $this->assertSomeoneCanStillAdminister($active);
        }

        $this->backup($unmigrated);

        if ($unmigrated !== []) {
            Log::warning('Roles non migres vers un poste de l\'organigramme : a replacer a la main.', ['unmigrated' => $unmigrated]);
        }

        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }

    public function down(): void
    {
        throw new RuntimeException('Migration irreversible : restaurez les anciens roles depuis la sauvegarde storage/app/backups/roles-*.json.');
    }

    private function transfer(object $assignment): bool
    {
        $key = match ($assignment->role) {
            'foundation-president' => $this->isFree('presidency') ? 'presidency' : 'national-administrators',
            'national-bureau-administrator' => $assignment->entity_id === null ? 'national-administrators' : null,
            'entity-administrator' => $assignment->entity_id === null ? 'national-administrators' : (self::ENTITY_OFFICES[$assignment->entity] ?? null),
            'former-president' => 'former-president',
            default => null,
        };

        $node = $key ? OrgNode::query()->where('system_key', $key)->first() : null;
        $user = User::query()->find($assignment->user_id);

        if (! $node || ! $user) {
            return false;
        }

        $person = OrgPerson::query()->firstOrCreate(['user_id' => $user->id], ['full_name' => $user->name, 'contact_visible' => true]);

        // Un poste a titulaire unique deja pourvu par une autre personne n'est pas surcharge.
        if ($node->capacity !== 'multiple' && OrgAssignment::query()->where('node_id', $node->id)->where('person_id', '!=', $person->id)->exists()) {
            return false;
        }

        OrgAssignment::query()->firstOrCreate(['node_id' => $node->id, 'person_id' => $person->id]);

        return true;
    }

    private function isFree(string $systemKey): bool
    {
        $node = OrgNode::query()->where('system_key', $systemKey)->first();

        return $node !== null && ! OrgAssignment::query()->where('node_id', $node->id)->exists();
    }

    /**
     * Sans administrateur global, plus personne ne pourrait modifier l'organigramme ni attribuer de droits :
     * on abandonne avant de supprimer quoi que ce soit.
     *
     * @param \Illuminate\Support\Collection<int, object> $active
     */
    private function assertSomeoneCanStillAdminister($active): void
    {
        $hadAdmin = $active->contains(fn ($row) => in_array($row->role, ['foundation-president', 'national-bureau-administrator'], true) && $row->entity_id === null);

        if (! $hadAdmin) {
            return;
        }

        $globalNodes = OrgNode::query()->where('access_level', 'global_admin')->pluck('id');

        if (! OrgAssignment::query()->whereIn('node_id', $globalNodes)->exists()) {
            throw new RuntimeException('Migration abandonnee : aucun administrateur global n\'a pu etre place sur un poste de l\'organigramme. Rien n\'a ete supprime.');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $unmigrated
     */
    private function backup(array $unmigrated): void
    {
        if (! DB::table('roles')->exists() && ! DB::table('role_assignments')->exists()) {
            return;
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        File::put($directory.'/roles-'.now()->format('Ymd-His').'.json', json_encode([
            'exported_at' => now()->toIso8601String(),
            'roles' => DB::table('roles')->get()->all(),
            'permissions' => DB::table('permissions')->get()->all(),
            'role_permissions' => DB::table('role_permissions')->get()->all(),
            'role_assignments' => DB::table('role_assignments')->get()->all(),
            'unmigrated' => $unmigrated,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
};
