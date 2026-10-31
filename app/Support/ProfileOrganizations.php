<?php

namespace App\Support;

use App\Models\AcademyEnrollment;
use App\Models\Company;
use App\Models\Institution;
use App\Models\MandateParticipation;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\Profile;
use App\Support\Academy\AcademySettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Relie les expériences d'un profil aux bases de la plateforme, pour y afficher leurs logos :
 *  - entreprise (expérience pro) : sélectionnée dans la base si elle existe, sinon créée ;
 *  - école (formation) : retrouvée parmi les établissements de la plateforme (jamais créée) ;
 *  - club (expérience associative) : retrouvé parmi les clubs.
 * Les profils gardent le nom saisi (texte libre) ; le lien (`company_id`, `school_id`, `club_id`) ne sert qu'à retrouver le logo.
 */
class ProfileOrganizations
{
    /** Libellé du rôle d'une adhésion (types hérités inclus) ; un type inconnu est affiché lisiblement, sans tiret bas. */
    private const MEMBERSHIP_TYPES = [
        'member' => 'Membre', 'president' => 'Président(e)', 'coordinator' => 'Coordinateur', 'vice_president' => 'Vice-président(e)',
        'responsible' => 'Responsable', 'assistant' => 'Assistant(e)', 'manager' => 'Manager', 'project_manager' => 'Project Manager',
        'advisor' => 'Conseiller', 'executive_manager' => 'Exécutif manager', 'tunifan' => 'TuniFan',
    ];

    /** @var Collection<int, OrgNode>|null */
    private ?Collection $clubs = null;

    /** Clé de comparaison : « Esprit », « ESPRIT » et « esprit » désignent la même organisation. */
    public static function key(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return Str::slug($name) ?: ($name === '' ? '' : 'x-'.md5(mb_strtolower($name)));
    }

    /** @return array<int, string> */
    public function companyOptions(): array
    {
        return Company::query()->orderBy('name')->pluck('name')->all();
    }

    /** @return array<int, string> */
    public function schoolOptions(): array
    {
        return Institution::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
    }

    /** @return array<int, string> */
    public function clubOptions(): array
    {
        return $this->clubs()->pluck('title')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /** Entreprise de la base portant ce nom ; créée si elle n'existe pas. */
    public function resolveCompany(string $name, ?int $creatorId = null): ?Company
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $slug = self::key($name);

        if ($slug === '') {
            return null;
        }

        return Company::query()->firstOrCreate(['slug' => $slug], ['name' => Str::limit($name, 160, ''), 'created_by' => $creatorId]);
    }

    /** Établissement de la plateforme portant ce nom. */
    public function findSchool(string $name): ?Institution
    {
        $key = self::key($name);

        return $key === '' ? null : Institution::query()->where('slug', $key)->first();
    }

    /** Club portant ce nom (celui qui a un logo est préféré). */
    public function findClub(string $name): ?OrgNode
    {
        $key = self::key($name);

        if ($key === '') {
            return null;
        }

        $matches = $this->clubs()->filter(fn (OrgNode $node): bool => self::key((string) $node->title) === $key);

        return $matches->first(fn (OrgNode $node): bool => $node->logoUrl() !== null || $node->institution?->logoUrl() !== null) ?? $matches->first();
    }

    /**
     * Ajoute `company_id`, `school_id` et `club_id` aux lignes des sections du profil.
     *
     * @param  array<string, mixed>  $settings  contenu de `privacy_settings`
     * @return array<string, mixed>
     */
    public function link(array $settings, ?int $creatorId = null): array
    {
        $sections = [
            'professional_experiences' => ['company', 'company_id', fn (string $name): ?int => $this->resolveCompany($name, $creatorId)?->id],
            'educations' => ['school', 'school_id', fn (string $name): ?int => $this->findSchool($name)?->id],
            'associative_experiences' => ['club_name', 'club_id', fn (string $name): ?int => $this->findClub($name)?->id],
        ];

        foreach ($sections as $section => [$nameKey, $idKey, $resolve]) {
            if (! isset($settings[$section]) || ! is_array($settings[$section])) {
                continue;
            }

            $settings[$section] = array_map(function (array $row) use ($nameKey, $idKey, $resolve): array {
                $name = trim((string) ($row[$nameKey] ?? ''));
                $row[$idKey] = $name === '' ? null : $resolve($name);

                return $row;
            }, $settings[$section]);
        }

        return $settings;
    }

    /**
     * Ajoute `logo_url` à chaque ligne d'une section ; le lien enregistré prime, à défaut on retrouve l'organisation par son nom.
     *
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  'company'|'school'|'club'  $type
     * @return Collection<int, array<string, mixed>>
     */
    public function withLogos(iterable $rows, string $type): Collection
    {
        $rows = collect($rows)->values();
        [$nameKey, $idKey] = match ($type) {
            'company' => ['company', 'company_id'],
            'school' => ['school', 'school_id'],
            default => ['club_name', 'club_id'],
        };

        if ($type === 'company') {
            $ids = $rows->pluck($idKey)->filter()->map(fn ($id): int => (int) $id)->all();
            $slugs = $rows->map(fn (array $row): string => self::key((string) ($row[$nameKey] ?? '')))->filter()->all();
            $companies = Company::query()->whereIn('id', $ids)->orWhereIn('slug', $slugs)->get();

            return $rows->map(function (array $row) use ($companies, $nameKey, $idKey): array {
                $company = $companies->firstWhere('id', (int) ($row[$idKey] ?? 0))
                    ?? $companies->firstWhere('slug', self::key((string) ($row[$nameKey] ?? '')));
                $row['logo_url'] = $company?->logoUrl();
                $row['url'] = $company?->safeUrl();

                return $row;
            });
        }

        if ($type === 'school') {
            $ids = $rows->pluck($idKey)->filter()->map(fn ($id): int => (int) $id)->all();
            $slugs = $rows->map(fn (array $row): string => self::key((string) ($row[$nameKey] ?? '')))->filter()->all();
            $institutions = Institution::query()->with(['parent:id,name', 'governorate:id,name'])
                ->where(fn ($query) => $query->whereIn('id', $ids)->orWhereIn('slug', $slugs))->get();

            return $rows->map(function (array $row) use ($institutions, $nameKey, $idKey): array {
                $institution = $institutions->firstWhere('id', (int) ($row[$idKey] ?? 0))
                    ?? $institutions->firstWhere('slug', self::key((string) ($row[$nameKey] ?? '')));
                $row['logo_url'] = $institution?->logoUrl();
                $row['url'] = $institution?->safeUrl();
                $row['info'] = $institution?->summary();

                return $row;
            });
        }

        return $rows->map(function (array $row) use ($nameKey, $idKey): array {
            $club = $this->clubs()->firstWhere('id', (int) ($row[$idKey] ?? 0)) ?? $this->findClub((string) ($row[$nameKey] ?? ''));
            $row['logo_url'] = $club?->logoUrl() ?? $club?->institution?->logoUrl();
            $row['url'] = null;
            $row['info'] = $club ? collect([$club->governorate?->name, $club->institution?->name])->filter()->implode(' · ') : null;

            return $row;
        });
    }

    /**
     * Sections d'un profil prêtes à afficher (logos inclus), pour la page du membre et celle de ses pairs.
     *
     * @return array<string, Collection<int, mixed>>
     */
    public function sections(Profile $profile): array
    {
        $settings = is_array($profile->privacy_settings) ? $profile->privacy_settings : [];
        $experiences = $this->newestFirst($this->withLogos($settings['professional_experiences'] ?? [], 'company'));
        $educations = $this->newestFirst($this->withLogos($settings['educations'] ?? [], 'school'));

        // En-tête du profil : entreprise actuelle (poste sans date de fin, sinon le premier) et établissement principal.
        $current = $experiences->first(fn (array $row): bool => blank($row['end_date'] ?? null) && filled($row['company'] ?? null))
            ?? $experiences->first(fn (array $row): bool => filled($row['company'] ?? null));
        $school = $educations->first(fn (array $row): bool => filled($row['school'] ?? null));

        return [
            'highlights' => collect([
                $current ? ['kind' => 'company', 'name' => $current['company'], 'role' => $current['role'] ?? null, 'logo_url' => $current['logo_url'], 'url' => $current['url'] ?? null, 'info' => null] : null,
                $school ? ['kind' => 'school', 'name' => $school['school'], 'role' => $school['diploma_name'] ?? null, 'logo_url' => $school['logo_url'], 'url' => $school['url'] ?? null, 'info' => $school['info'] ?? null] : null,
            ])->filter()->values(),
            'professionalExperiences' => $experiences,
            'educations' => $educations,
            'certifications' => collect($settings['certifications'] ?? [])->values(),
            'associativeExperiences' => $this->newestFirst($this->withLogos($settings['associative_experiences'] ?? [], 'club')),
            'foundationMemberships' => $profile->user_id ? $this->foundationMemberships((int) $profile->user_id) : collect(),
            'academyCertificates' => $profile->user_id ? $this->academyCertificates((int) $profile->user_id) : collect(),
            'skillKeywords' => collect($settings['skill_keywords'] ?? [])->values(),
            'languageItems' => collect($settings['languages'] ?? [])->values(),
        ];
    }

    /**
     * Adhésions validées du membre, ajoutées automatiquement à ses expériences associatives :
     * adhésions approuvées rattachées à un club ou une unité, et participations acceptées à d'anciens mandats.
     * Les demandes en attente ou refusées, et les adhésions sans rattachement, n'apparaissent pas.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function foundationMemberships(int $userId): Collection
    {
        $rows = Membership::query()
            ->where('user_id', $userId)->where('status', 'approved')->whereNotNull('node_id')
            ->whereHas('node')->with('node')->get()
            ->map(function (Membership $membership): array {
                $club = $this->clubOf($membership->node);
                $isClub = $membership->node->kind === 'club';

                return [
                    'club_name' => $club?->title ?? $membership->node->title,
                    'role' => self::MEMBERSHIP_TYPES[$membership->membership_type] ?? ($membership->membership_type ? ucfirst(str_replace('_', ' ', $membership->membership_type)) : 'Membre'),
                    'department' => $isClub ? null : $membership->node->title,
                    'start_date' => ($membership->starts_at ?? $membership->approved_at ?? $membership->created_at)?->toDateString(),
                    'end_date' => $membership->ends_at?->toDateString(),
                    'mandate' => null,
                    'logo_url' => $club?->logoUrl() ?? $club?->institution?->logoUrl(),
                    'info' => $club ? collect([$club->governorate?->name, $club->institution?->name])->filter()->implode(' · ') : null,
                    'key' => ($club?->id ?? $membership->node_id).'|'.($membership->membership_type ?: 'member'),
                ];
            });

        $participations = MandateParticipation::query()
            ->where('user_id', $userId)->where('status', 'approved')->whereNotNull('node_id')
            ->whereHas('node')->with(['node', 'mandate'])->get()
            ->map(function (MandateParticipation $participation): array {
                $club = $this->clubOf($participation->node);
                $isClub = $participation->node->kind === 'club';
                $mandate = $participation->mandate;

                return [
                    'club_name' => $club?->title ?? $participation->node->title,
                    'role' => $participation->position_title ?: 'Membre',
                    'department' => $isClub ? null : $participation->node->title,
                    'start_date' => $mandate?->start_year ? $mandate->start_year.'-09-01' : null,
                    'end_date' => $mandate?->end_year ? $mandate->end_year.'-08-31' : null,
                    'mandate' => $mandate?->label,
                    'logo_url' => $club?->logoUrl() ?? $club?->institution?->logoUrl(),
                    'info' => $club ? collect([$club->governorate?->name, $club->institution?->name])->filter()->implode(' · ') : null,
                    'key' => 'p'.$participation->id,
                ];
            });

        return $this->newestFirst($rows->concat($participations)->unique('key')->values());
    }

    /**
     * Certificats obtenus sur Tunivisions Academy (cours terminés), vérifiables grâce à leur code.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function academyCertificates(int $userId): Collection
    {
        $logo = AcademySettings::fileUrl('logo_path');

        return AcademyEnrollment::query()
            ->where('user_id', $userId)->whereNotNull('completed_at')->whereNotNull('certificate_code')
            ->with('course:id,title')->orderByDesc('completed_at')->orderByDesc('id')->get()
            ->filter(fn (AcademyEnrollment $enrollment): bool => $enrollment->course !== null)
            ->map(fn (AcademyEnrollment $enrollment): array => [
                'name' => $enrollment->course->title,
                'issuer' => AcademySettings::NAME,
                'date_obtained' => $enrollment->completed_at->toDateString(),
                'code' => $enrollment->certificate_code,
                'url' => route('academy.certificate', $enrollment->certificate_code),
                'logo_url' => $logo,
            ])
            ->values();
    }

    /** Club auquel appartient un bloc : lui-même s'il en est un, sinon son plus proche parent qui en est un. */
    private function clubOf(?OrgNode $node): ?OrgNode
    {
        for ($guard = 0; $guard < 20 && $node !== null; $guard++) {
            if ($node->kind === 'club') {
                return $this->clubs()->firstWhere('id', $node->id) ?? $node->loadMissing(['institution', 'governorate']);
            }

            $node = $node->parent_id !== null ? OrgNode::query()->find($node->parent_id) : null;
        }

        return null;
    }

    /**
     * Du plus récent au plus ancien selon la date de début ; sans date de début en dernier,
     * et à date égale l'expérience en cours (sans date de fin) d'abord.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function newestFirst(Collection $rows): Collection
    {
        return $rows
            ->sort(function (array $a, array $b): int {
                $byStart = strcmp((string) ($b['start_date'] ?? ''), (string) ($a['start_date'] ?? ''));

                if ($byStart !== 0) {
                    return $byStart;
                }

                $end = fn (array $row): string => blank($row['end_date'] ?? null) ? '9999-12-31' : (string) $row['end_date'];

                return strcmp($end($b), $end($a));
            })
            ->values();
    }

    /** Rattache les profils existants (entreprises créées au besoin, écoles et clubs retrouvés). */
    public function backfill(): void
    {
        Profile::query()->whereNotNull('privacy_settings')->chunkById(200, function ($profiles): void {
            foreach ($profiles as $profile) {
                $settings = $profile->privacy_settings;

                if (! is_array($settings)) {
                    continue;
                }

                $linked = $this->link($settings, $profile->user_id);

                if ($linked !== $settings) {
                    $profile->privacy_settings = $linked;
                    $profile->saveQuietly();
                }
            }
        });
    }

    /** @return Collection<int, OrgNode> */
    private function clubs(): Collection
    {
        return $this->clubs ??= OrgNode::query()->where('kind', 'club')->with(['governorate:id,name', 'institution:id,name,logo_path'])->orderBy('id')
            ->get(['id', 'title', 'institution_name', 'institution_id', 'governorate_id', 'logo_path']);
    }
}
