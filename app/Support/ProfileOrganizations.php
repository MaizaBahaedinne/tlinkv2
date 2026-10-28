<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Institution;
use App\Models\OrgNode;
use App\Models\Profile;
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
        $experiences = $this->withLogos($settings['professional_experiences'] ?? [], 'company');
        $educations = $this->withLogos($settings['educations'] ?? [], 'school');

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
            'associativeExperiences' => $this->withLogos($settings['associative_experiences'] ?? [], 'club'),
            'skillKeywords' => collect($settings['skill_keywords'] ?? [])->values(),
            'languageItems' => collect($settings['languages'] ?? [])->values(),
        ];
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
