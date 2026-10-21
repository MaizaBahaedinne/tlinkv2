<?php

namespace App\Support\OrgChart;

use App\Models\Governorate;
use App\Models\OrgNode;
use App\Support\OrgScopes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Installe l'organigramme initial et cree les clubs avec leur equipe type.
 * Toutes les operations sont idempotentes (cle `system_key`) : relancer l'installation ne cree aucun doublon.
 *
 * Les noms de personnes ne sont JAMAIS renseignes : tous les postes sont crees vacants.
 */
class OrgChartInstaller
{
    /** Les 24 gouvernorats / clubs regionaux « We Are ». */
    public const REGIONAL_CLUBS = [
        'Tunis', 'Ariana', 'Ben Arous', 'Manouba', 'Nabeul', 'Zaghouan',
        'Bizerte', 'Béja', 'Jendouba', 'Le Kef', 'Siliana', 'Sousse',
        'Monastir', 'Mahdia', 'Sfax', 'Kairouan', 'Kasserine', 'Sidi Bouzid',
        'Gabès', 'Médenine', 'Tataouine', 'Gafsa', 'Tozeur', 'Kébili',
    ];

    private const TO_VALIDATE_NOTE = 'Rattachement déduit du descriptif : à confirmer par un administrateur.';

    /** Departements types d'un bureau national et d'un club : slug => libelle. */
    public const DEPARTMENT_SLOTS = ['marketing' => 'Marketing', 'hr' => 'RH', 'events' => 'Events', 'rd' => 'R&D'];

    private const CLUB_CATEGORIES = ['regional_club', 'university_club', 'highschool_club'];

    public function __construct(private readonly OrgChartLinker $linker)
    {
    }

    public function isInstalled(): bool
    {
        return OrgNode::query()->exists();
    }

    public function install(): void
    {
        DB::transaction(function (): void {
            $president = $this->node('presidency', null, 'presidency', 'Président de la fondation', ['access_level' => 'global_admin']);
            $this->ensureAdministrators($president);

            // Rattachements a la presidence : pas de lien hierarchique avec le directeur executif.
            $this->node('president-advisors', $president, 'presidency', 'Conseillers du président', [
                'relation_type' => 'attachment',
                'description' => 'Conseillers rattachés à la présidence. Ils ne sont pas supérieurs hiérarchiques du directeur exécutif.',
            ]);
            $this->node('former-president', $president, 'presidency', 'Ancien président de la fondation', [
                'relation_type' => 'attachment',
                'description' => 'Rattaché à la présidence. Pas de lien hiérarchique avec le directeur exécutif.',
            ]);

            $director = $this->node('executive-director', $president, 'direction', 'Directeur exécutif', [
                'description' => 'Coordonne les bureaux nationaux et les responsables des projets.',
            ]);

            $this->installNationalOffice($director, 'university', 'Coordinateur national University', 'Bureau national University', 'University Clubs', 'university_club');
            $this->installNationalOffice($director, 'highschool', 'Coordinateur national Highschool', 'Bureau national Highschool', 'High School Clubs', 'highschool_club');

            $carthage = $this->node('we-are-carthage', $director, 'project', 'Chef de projet We Are Carthage', [
                'subtitle' => 'Bureau We Are Carthage',
                'children_layout' => 'vertical',
                'validation_status' => 'to_validate',
                'validation_note' => self::TO_VALIDATE_NOTE,
            ]);
            $this->node('we-are-carthage-managers', $carthage, 'project', 'Managers', ['subtitle' => 'We Are Carthage', 'capacity' => 'multiple']);
            $this->node('we-are-carthage-operations', $carthage, 'project', 'Équipe opérationnelle', [
                'subtitle' => 'We Are Carthage',
                'capacity' => 'multiple',
                'validation_status' => 'to_validate',
                'validation_note' => 'Équipe prévue « si nécessaire » : à confirmer.',
            ]);
            $network = $this->node('regional-clubs', $carthage, 'regional_club', 'Réseau des clubs régionaux We Are', [
                'kind' => 'unit',
                'children_layout' => 'vertical',
                'description' => 'Un club « We Are » par gouvernorat de Tunisie.',
            ]);

            $campers = $this->node('we-are-campers', $director, 'project', 'Chef de projet We Are Campers', [
                'subtitle' => 'Projet We Are Campers',
                'children_layout' => 'vertical',
                'validation_status' => 'to_validate',
                'validation_note' => self::TO_VALIDATE_NOTE,
            ]);
            $this->node('we-are-campers-managers', $campers, 'project', 'Managers', ['subtitle' => 'We Are Campers', 'capacity' => 'multiple']);
            $this->node('we-are-campers-operations', $campers, 'project', 'Équipe opérationnelle', [
                'subtitle' => 'We Are Campers',
                'capacity' => 'multiple',
                'validation_status' => 'to_validate',
                'validation_note' => 'Équipe prévue « si nécessaire » : à confirmer.',
            ]);

            $alumni = $this->node('alumni', $director, 'project', 'Coordinateur national Alumni', [
                'subtitle' => 'Réseau Alumni',
                'access_level' => 'entity_admin',
                'children_layout' => 'vertical',
                'validation_status' => 'to_validate',
                'validation_note' => self::TO_VALIDATE_NOTE,
            ]);
            $this->node('alumni-managers', $alumni, 'project', 'Managers', ['subtitle' => 'Réseau Alumni', 'capacity' => 'multiple']);
            $this->node('alumni-operations', $alumni, 'project', 'Équipe opérationnelle', [
                'subtitle' => 'Réseau Alumni',
                'capacity' => 'multiple',
                'validation_status' => 'to_validate',
                'validation_note' => 'Équipe prévue « si nécessaire » : à confirmer.',
            ]);

            foreach (self::REGIONAL_CLUBS as $governorateName) {
                $key = 'regional-club:'.Str::slug($governorateName);

                if (OrgNode::query()->where('system_key', $key)->exists()) {
                    continue;
                }

                $governorate = $this->governorate($governorateName);

                $this->createClub($network, 'We Are '.$governorateName, 'regional_club', $governorate, true, $key);
            }
        });

        // Relie les blocs systeme a la structure et ajoute les clubs deja presents en base (import legacy...).
        $this->restructure();
        $this->linker->sync();
    }

    /**
     * Range les postes des bureaux nationaux et des clubs sous des departements (anciens organigrammes
     * ou la hierarchie passait directement du coordinateur / president aux managers / vice-presidents).
     * Idempotent : un poste deja dans un departement n'est pas retouche.
     *
     * @return int nombre de departements crees
     */
    public function restructure(): int
    {
        return DB::transaction(function (): int {
            $created = 0;

            if ($president = OrgNode::query()->where('system_key', 'presidency')->first()) {
                $this->ensureAdministrators($president);
            }

            foreach (['university', 'highschool'] as $key) {
                $coordinator = OrgNode::query()->where('system_key', $key.'-office')->first();

                if (! $coordinator) {
                    continue;
                }

                foreach (self::DEPARTMENT_SLOTS as $slug => $label) {
                    $manager = OrgNode::query()->where('system_key', $key.'-manager-'.$slug)->first();
                    $deptKey = $key.'-dept-'.$slug;

                    if (! $manager || (int) $manager->parent_id !== (int) $coordinator->id || OrgNode::query()->where('system_key', $deptKey)->exists()) {
                        continue;
                    }

                    $department = OrgNode::query()->create([
                        'parent_id' => $coordinator->id,
                        'relation_type' => 'hierarchy',
                        'kind' => 'department',
                        'category' => 'national_office',
                        'title' => 'Département '.$label,
                        'subtitle' => $manager->subtitle,
                        'sort_order' => $manager->sort_order,
                        'system_key' => $deptKey,
                    ]);

                    $manager->update(['parent_id' => $department->id, 'sort_order' => 0]);
                    $created++;
                }
            }

            $titles = [];

            foreach (self::DEPARTMENT_SLOTS as $label) {
                $titles['Vice-président '.$label] = $label;
                $titles['VP '.$label] = $label;
            }

            $vicePresidents = OrgNode::query()->with('parent')
                ->where('kind', 'position')
                ->whereIn('title', array_keys($titles))
                ->whereIn('category', self::CLUB_CATEGORIES)
                ->get();

            foreach ($vicePresidents as $vp) {
                if (! $vp->parent || $vp->parent->kind !== 'position') {
                    continue;
                }

                $department = OrgNode::query()->create([
                    'parent_id' => $vp->parent_id,
                    'relation_type' => 'hierarchy',
                    'kind' => 'club_department',
                    'category' => $vp->category,
                    'title' => 'Département '.$titles[$vp->title],
                    'subtitle' => $vp->subtitle,
                    'sort_order' => $vp->sort_order,
                ]);

                $vp->update(['parent_id' => $department->id, 'sort_order' => 0]);
                $created++;
            }

            return $created;
        });
    }

    /**     * Poste des administrateurs de la plateforme : la place des anciens rôles « administrateur national ».
     * Seul niveau d'accès global : on y affecte les personnes qui administrent toute la plateforme.
     */
    public function ensureAdministrators(OrgNode $president): OrgNode
    {
        return $this->node('national-administrators', $president, 'presidency', 'Administrateurs de la plateforme', [
            'subtitle' => 'Bureau national',
            'relation_type' => 'attachment',
            'capacity' => 'multiple',
            'access_level' => 'global_admin',
            'description' => 'Les personnes affectées à ce poste administrent toute la plateforme T.Link.',
        ]);
    }

    /**
     * Niveaux d'accès par défaut des postes existants (migration des anciens rôles) : ne touche que les postes sans niveau.
     *
     * @return int nombre de postes mis à jour
     */
    public function applyDefaultAccess(): int
    {
        $count = 0;

        $set = function (OrgNode $node, string $level) use (&$count): void {
            if ($node->access_level === 'none' && $node->kind === 'position') {
                $node->update(['access_level' => $level]);
                $count++;
            }
        };

        foreach (['presidency' => 'global_admin', 'national-administrators' => 'global_admin', 'university-office' => 'entity_admin', 'highschool-office' => 'entity_admin', 'alumni' => 'entity_admin'] as $key => $level) {
            if ($node = OrgNode::query()->where('system_key', $key)->first()) {
                $set($node, $level);
            }
        }

        OrgNode::query()->where('system_key', 'like', '%-manager-%')->get()->each(fn (OrgNode $node) => $set($node, 'manager'));

        OrgNode::query()->where('kind', 'position')->where('title', 'like', 'Président%')
            ->whereIn('parent_id', OrgNode::query()->where('kind', 'club')->pluck('id'))
            ->get()->each(fn (OrgNode $node) => $set($node, 'club_president'));

        return $count;
    }

    /**
     * Cree un club (bloc de l'organigramme) avec l'equipe type.
     * Un club d'universite ou de lycee est range par gouvernorat et porte son etablissement.
     */
    public function createClub(OrgNode $branch, string $name, string $category, ?Governorate $governorate, bool $withTemplate, ?string $systemKey = null): OrgNode
    {
        return DB::transaction(function () use ($branch, $name, $category, $governorate, $withTemplate, $systemKey): OrgNode {
            $scope = match ($category) {
                'university_club' => 'university',
                'highschool_club' => 'high-school',
                default => 'foundation',
            };
            $institutionType = OrgScopes::institutionType($scope);

            // Les clubs d'universite / de lycee sont ranges par gouvernorat.
            $parent = in_array($category, ['university_club', 'highschool_club'], true)
                ? $this->linker->groupFor($branch, $governorate)
                : $branch;

            $node = OrgNode::query()->create([
                'parent_id' => $parent->id,
                'relation_type' => 'hierarchy',
                'kind' => 'club',
                'category' => $category,
                'title' => $name,
                'subtitle' => $governorate?->name,
                'governorate_id' => $governorate?->id,
                'scope' => $scope,
                'status' => 'active',
                'institution_name' => $institutionType !== null ? $name : null,
                'institution_type' => $institutionType,
                'is_collapsed' => true,
                'sort_order' => $this->nextSort($parent->id),
                'system_key' => $systemKey,
            ]);

            if ($withTemplate) {
                $this->applyTemplate($node);
            }

            return $node;
        });
    }

    /**
     * Ajoute l'equipe type a un club. Modele par defaut : l'administrateur peut ensuite tout modifier.
     */
    public function applyTemplate(OrgNode $clubNode): int
    {
        $regional = $clubNode->category === 'regional_club';
        $created = [];
        $count = 0;

        foreach ($this->clubTemplate($regional) as $slot => $definition) {
            $parentId = $definition['parent'] === null ? $clubNode->id : (isset($created[$definition['parent']]) ? $created[$definition['parent']]->id : $clubNode->id);

            $created[$slot] = OrgNode::query()->create([
                'parent_id' => $parentId,
                'relation_type' => 'hierarchy',
                'kind' => $definition['kind'],
                'capacity' => $definition['capacity'],
                'access_level' => $definition['access'],
                'category' => $clubNode->category,
                'title' => $definition['title'],
                'subtitle' => $clubNode->title,
                'validation_status' => $definition['validate'] ? 'to_validate' : 'confirmed',
                'validation_note' => $definition['validate'] ? 'Rattachement du modèle par défaut : à adapter à la réalité du club.' : null,
                'sort_order' => $this->nextSort($parentId),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, array{title: string, parent: string|null, validate: bool, kind: string, capacity: string, access: string}>
     */
    public function clubTemplate(bool $regional): array
    {
        $slot = static fn (string $title, ?string $parent, bool $validate = false, string $kind = 'position', string $capacity = 'single', string $access = 'none'): array => [
            'title' => $title, 'parent' => $parent, 'validate' => $validate, 'kind' => $kind, 'capacity' => $capacity, 'access' => $access,
        ];

        $slots = ['president' => $slot($regional ? 'Président du club régional' : 'Président de club', null, false, 'position', 'single', 'club_president')];

        foreach (self::DEPARTMENT_SLOTS as $slug => $label) {
            $slots['dept-'.$slug] = $slot('Département '.$label, 'president', false, 'club_department');
            $slots['vp-'.$slug] = $slot(($regional ? 'Vice-président ' : 'VP ').$label, 'dept-'.$slug);
        }

        if ($regional) {
            $slots['members'] = $slot('Assistants et membres', 'president', true, 'position', 'multiple');

            return $slots;
        }

        $slots['assistant-marketing'] = $slot('Assistant Marketing', 'vp-marketing', true, 'position', 'multiple');
        $slots['assistant-hr'] = $slot('Assistant RH', 'vp-hr', true, 'position', 'multiple');
        $slots['others'] = $slot('Autres membres et responsables', 'president', true, 'position', 'multiple');

        return $slots;
    }

    private function installNationalOffice(OrgNode $director, string $key, string $coordinatorTitle, string $office, string $clubsBranchTitle, string $clubCategory): void
    {
        $coordinator = $this->node($key.'-office', $director, 'national_office', $coordinatorTitle, [
            'subtitle' => $office,
            'children_layout' => 'vertical',
            'validation_status' => 'to_validate',
            'validation_note' => self::TO_VALIDATE_NOTE,
            'description' => 'Coordonne les activités destinées aux '.($key === 'university' ? 'étudiants universitaires' : 'lycéens').'.',
            'access_level' => 'entity_admin',
        ]);

        foreach (self::DEPARTMENT_SLOTS as $slug => $label) {
            $department = $this->node($key.'-dept-'.$slug, $coordinator, 'national_office', 'Département '.$label, ['kind' => 'department', 'subtitle' => $office]);
            $manager = $this->node($key.'-manager-'.$slug, $department, 'national_office', 'Manager '.$label, ['subtitle' => $office, 'access_level' => 'manager']);

            if (in_array($slug, ['marketing', 'hr'], true)) {
                $this->node($key.'-assistant-'.$slug, $manager, 'national_office', 'Assistant '.$label, ['subtitle' => $office, 'capacity' => 'multiple']);
            }
        }

        // Branche des clubs : volontairement vide, les clubs se creent depuis l'interface.
        $this->node($key.'-clubs', $coordinator, $clubCategory, $clubsBranchTitle, [
            'kind' => 'unit',
            'children_layout' => 'vertical',
            'validation_status' => 'to_validate',
            'validation_note' => 'Position de la branche des clubs sous le coordinateur : à confirmer.',
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function node(string $key, ?OrgNode $parent, string $category, string $title, array $attributes = []): OrgNode
    {
        $existing = OrgNode::query()->where('system_key', $key)->first();

        if ($existing) {
            return $existing;
        }

        return OrgNode::query()->create($attributes + [
            'parent_id' => $parent?->id,
            'category' => $category,
            'title' => $title,
            'sort_order' => $this->nextSort($parent?->id),
            'system_key' => $key,
        ]);
    }

    private function nextSort(?int $parentId): int
    {
        $max = OrgNode::query()->where('parent_id', $parentId)->max('sort_order');

        return $max === null ? 0 : ((int) $max) + 1;
    }

    private function governorate(string $name): Governorate
    {
        return Governorate::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true]
        );
    }
}
