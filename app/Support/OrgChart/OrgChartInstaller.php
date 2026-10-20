<?php

namespace App\Support\OrgChart;

use App\Models\Club;
use App\Models\Entity;
use App\Models\Governorate;
use App\Models\Institution;
use App\Models\OrgNode;
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
            $president = $this->node('presidency', null, 'presidency', 'Président de la fondation');

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
            $this->node('we-are-carthage-managers', $carthage, 'project', 'Managers', ['subtitle' => 'We Are Carthage']);
            $this->node('we-are-carthage-operations', $carthage, 'project', 'Équipe opérationnelle', [
                'subtitle' => 'We Are Carthage',
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
            $this->node('we-are-campers-managers', $campers, 'project', 'Managers', ['subtitle' => 'We Are Campers']);
            $this->node('we-are-campers-operations', $campers, 'project', 'Équipe opérationnelle', [
                'subtitle' => 'We Are Campers',
                'validation_status' => 'to_validate',
                'validation_note' => 'Équipe prévue « si nécessaire » : à confirmer.',
            ]);

            $alumni = $this->node('alumni', $director, 'project', 'Coordinateur national Alumni', [
                'subtitle' => 'Réseau Alumni',
                'children_layout' => 'vertical',
                'validation_status' => 'to_validate',
                'validation_note' => self::TO_VALIDATE_NOTE,
            ]);
            $this->node('alumni-managers', $alumni, 'project', 'Managers', ['subtitle' => 'Réseau Alumni']);
            $this->node('alumni-operations', $alumni, 'project', 'Équipe opérationnelle', [
                'subtitle' => 'Réseau Alumni',
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
        $this->linker->sync();
    }

    /**
     * Cree un club (modele `clubs`) et son bloc dans l'organigramme, avec l'equipe type.
     */
    public function createClub(OrgNode $branch, string $name, string $category, ?Governorate $governorate, bool $withTemplate, ?string $systemKey = null): OrgNode
    {
        return OrgChartLinker::withoutSync(fn (): OrgNode => DB::transaction(function () use ($branch, $name, $category, $governorate, $withTemplate, $systemKey): OrgNode {
            $club = $this->clubModel($name, $category, $governorate);

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
                'club_id' => $club->id,
                'governorate_id' => $governorate?->id,
                'entity_id' => $club->entity_id,
                'department_id' => $club->department_id,
                'institution_id' => $club->institution_id,
                'is_collapsed' => true,
                'sort_order' => $this->nextSort($parent->id),
                'system_key' => $systemKey,
            ]);

            if ($withTemplate) {
                $this->applyTemplate($node);
            }

            return $node;
        }));
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
                'kind' => 'position',
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
     * @return array<string, array{title: string, parent: string|null, validate: bool}>
     */
    public function clubTemplate(bool $regional): array
    {
        if ($regional) {
            return [
                'president' => ['title' => 'Président du club régional', 'parent' => null, 'validate' => false],
                'vp-marketing' => ['title' => 'Vice-président Marketing', 'parent' => 'president', 'validate' => false],
                'vp-hr' => ['title' => 'Vice-président RH', 'parent' => 'president', 'validate' => false],
                'vp-events' => ['title' => 'Vice-président Events', 'parent' => 'president', 'validate' => false],
                'vp-rd' => ['title' => 'Vice-président R&D', 'parent' => 'president', 'validate' => false],
                'members' => ['title' => 'Assistants et membres', 'parent' => 'president', 'validate' => true],
            ];
        }

        return [
            'president' => ['title' => 'Président de club', 'parent' => null, 'validate' => false],
            'vp-marketing' => ['title' => 'VP Marketing', 'parent' => 'president', 'validate' => false],
            'vp-hr' => ['title' => 'VP RH', 'parent' => 'president', 'validate' => false],
            'vp-events' => ['title' => 'VP Events', 'parent' => 'president', 'validate' => false],
            'vp-rd' => ['title' => 'VP R&D', 'parent' => 'president', 'validate' => false],
            'assistant-marketing' => ['title' => 'Assistant Marketing', 'parent' => 'vp-marketing', 'validate' => true],
            'assistant-hr' => ['title' => 'Assistant RH', 'parent' => 'vp-hr', 'validate' => true],
            'others' => ['title' => 'Autres membres et responsables', 'parent' => 'president', 'validate' => true],
        ];
    }

    private function installNationalOffice(OrgNode $director, string $key, string $coordinatorTitle, string $office, string $clubsBranchTitle, string $clubCategory): void
    {
        $coordinator = $this->node($key.'-office', $director, 'national_office', $coordinatorTitle, [
            'subtitle' => $office,
            'children_layout' => 'vertical',
            'validation_status' => 'to_validate',
            'validation_note' => self::TO_VALIDATE_NOTE,
            'description' => 'Coordonne les activités destinées aux '.($key === 'university' ? 'étudiants universitaires' : 'lycéens').'.',
        ]);

        $marketing = $this->node($key.'-manager-marketing', $coordinator, 'national_office', 'Manager Marketing', ['subtitle' => $office]);
        $this->node($key.'-assistant-marketing', $marketing, 'national_office', 'Assistant Marketing', ['subtitle' => $office]);

        $hr = $this->node($key.'-manager-hr', $coordinator, 'national_office', 'Manager RH', ['subtitle' => $office]);
        $this->node($key.'-assistant-hr', $hr, 'national_office', 'Assistant RH', ['subtitle' => $office]);

        $this->node($key.'-manager-events', $coordinator, 'national_office', 'Manager Events', ['subtitle' => $office]);
        $this->node($key.'-manager-rd', $coordinator, 'national_office', 'Manager R&D', ['subtitle' => $office]);

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

    private function clubModel(string $name, string $category, ?Governorate $governorate): Club
    {
        $base = Str::slug($name) ?: 'club';
        $slug = $base;
        $counter = 1;

        // Un club deja present dans la base (meme nom) et non relie a l'organigramme est reutilise.
        while ($existing = Club::query()->where('slug', $slug)->first()) {
            if (! OrgNode::query()->where('club_id', $existing->id)->exists()) {
                return $existing;
            }

            $counter++;
            $slug = $base.'-'.$counter;
        }

        $entitySlug = match ($category) {
            'university_club' => 'university',
            'highschool_club' => 'high-school',
            default => 'foundation',
        };

        $entity = Entity::query()->where('slug', $entitySlug)->first();

        // Un club d'universite / de lycee a son etablissement (institution) et son departement.
        $institution = null;

        if ($entity && in_array($category, ['university_club', 'highschool_club'], true)) {
            $institution = Institution::query()->firstOrCreate(
                ['institution_type' => $category === 'university_club' ? 'university' : 'high_school', 'slug' => $base],
                ['entity_id' => $entity->id, 'name' => $name, 'is_active' => true]
            );
        }

        return Club::query()->create([
            'name' => $name,
            'slug' => $slug,
            'entity_id' => $entity?->id,
            'department_id' => $entity ? $this->linker->department($entity)?->id : null,
            'institution_id' => $institution?->id,
            'governorate_id' => $governorate?->id,
            'status' => 'active',
        ]);
    }
}
