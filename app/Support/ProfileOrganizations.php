<?php

namespace App\Support;

use App\Models\Company;
use App\Models\OrgNode;
use App\Models\Profile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Relie les expériences d'un profil aux bases de la plateforme, pour y afficher leurs logos :
 *  - entreprise (expérience pro) : sélectionnée dans la base si elle existe, sinon créée ;
 *  - école (formation) : retrouvée parmi les établissements des clubs (jamais créée) ;
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
        return $this->clubs()->pluck('institution_name')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
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

    /** Club dont l'établissement porte ce nom (celui qui a un logo est préféré). */
    public function findSchool(string $name): ?OrgNode
    {
        return $this->match($name, 'institution_name') ?? $this->match($name, 'title');
    }

    public function findClub(string $name): ?OrgNode
    {
        return $this->match($name, 'title') ?? $this->match($name, 'institution_name');
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

                return $row;
            });
        }

        return $rows->map(function (array $row) use ($type, $nameKey, $idKey): array {
            $node = $this->clubs()->firstWhere('id', (int) ($row[$idKey] ?? 0))
                ?? ($type === 'school' ? $this->findSchool((string) ($row[$nameKey] ?? '')) : $this->findClub((string) ($row[$nameKey] ?? '')));
            $row['logo_url'] = $node?->logoUrl();

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

        return [
            'professionalExperiences' => $this->withLogos($settings['professional_experiences'] ?? [], 'company'),
            'educations' => $this->withLogos($settings['educations'] ?? [], 'school'),
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
        return $this->clubs ??= OrgNode::query()->where('kind', 'club')->orderBy('id')->get(['id', 'title', 'institution_name', 'logo_path']);
    }

    private function match(string $name, string $field): ?OrgNode
    {
        $key = self::key($name);

        if ($key === '') {
            return null;
        }

        $matches = $this->clubs()->filter(fn (OrgNode $node): bool => $node->{$field} !== null && self::key((string) $node->{$field}) === $key);

        return $matches->first(fn (OrgNode $node): bool => $node->logo_path !== null) ?? $matches->first();
    }
}
