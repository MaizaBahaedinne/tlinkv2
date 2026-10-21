<?php

namespace App\Support;

use App\Models\Governorate;
use App\Models\OrgNode;
use App\Support\OrgChart\OrgChartInstaller;
use App\Support\OrgChart\OrgChartLinker;
use Illuminate\Support\Str;

/**
 * Importe les clubs du dump legacy (tbl_club) comme blocs « club » de l'organigramme.
 */
class LegacyClubImporter
{
    public function __construct(private readonly OrgChartInstaller $installer, private readonly OrgChartLinker $linker)
    {
    }

    /** Noms de clubs rattaches a un etablissement different de leur propre nom. */
    private const INSTITUTION_ALIASES = [
        'esprit tic' => 'ESPRIT',
        'esprit gcem' => 'ESPRIT',
        'lycée hammam lif bougarnin' => 'Lycée Hammam Lif Bougarnine',
        'ipeim' => 'IPEIM',
    ];

    public function import(string $dumpPath): array
    {
        if (! is_file($dumpPath)) {
            throw new \RuntimeException("Dump legacy introuvable: {$dumpPath}");
        }

        $stats = [
            'branches' => 0,
            'clubs' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'no_governorate' => [],
        ];

        $inClubTable = false;

        if (! $this->installer->isInstalled()) {
            $this->installer->install();
        }

        $handle = fopen($dumpPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir le dump legacy: {$dumpPath}");
        }

        while (($line = fgets($handle)) !== false) {
            if (! $inClubTable && str_contains($line, 'INSERT INTO `tbl_club`')) {
                $inClubTable = true;
                continue;
            }

            if ($inClubTable && str_starts_with(trim($line), 'INSERT INTO `tbl_')) {
                break;
            }

            if (! $inClubTable) {
                continue;
            }

            $trimmed = trim($line);
            if ($trimmed === '' || ! str_starts_with($trimmed, '(')) {
                continue;
            }

            $row = $this->parseRow($trimmed);
            if ($row === null) {
                $stats['skipped']++;
            } elseif (in_array($row['club_id'], [-1, 0, 1, 2], true)) {
                $stats['branches']++;
            } else {
                $result = $this->syncClub($row);
                $stats['clubs']++;
                $stats[$result['created'] ? 'created' : 'updated']++;
                if (! $result['governorate']) {
                    $stats['no_governorate'][] = $row['name'].' ('.$row['city'].')';
                }
            }

            // La derniere ligne du INSERT se termine par un point-virgule.
            if (str_ends_with($trimmed, ';')) {
                break;
            }
        }

        fclose($handle);

        return $stats;
    }

    /** @return array{created: bool, governorate: bool} */
    private function syncClub(array $row): array
    {
        $scope = $this->resolveScope($row);
        $governorate = $this->resolveGovernorate($row['city']);
        [$institutionName, $institutionType] = $this->institutionOf($row, $scope);

        // Dans l'ancienne base, les colonnes `facebook` et `email` sont inversees
        // sur de nombreuses lignes : on classe chaque valeur selon son contenu.
        $email = null;
        $facebook = null;
        foreach ([$row['facebook'], $row['email']] as $value) {
            if ($value === '') {
                continue;
            }
            if (preg_match('#^https?://#i', $value)) {
                $facebook ??= $value;
            } elseif (str_contains($value, '@')) {
                $email ??= $value;
            }
        }

        $founded = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['birthday']) && $row['birthday'] !== '0000-00-00' && strtotime($row['birthday']) !== false) {
            $founded = $row['birthday'];
        }

        $club = OrgNode::query()->where('legacy_id', $row['club_id'])->first() ?? new OrgNode();
        $created = ! $club->exists;

        if ($created) {
            $resolved = $this->linker->branchFor($scope);
            $parent = $resolved ? $this->linker->groupFor($resolved[0], $governorate) : $this->fallbackParent($scope);

            $max = OrgNode::query()->where('parent_id', $parent->id)->max('sort_order');
            $club->fill([
                'parent_id' => $parent->id,
                'relation_type' => 'hierarchy',
                'kind' => 'club',
                'category' => $resolved[1] ?? $parent->category,
                'is_collapsed' => true,
                'sort_order' => $max === null ? 0 : ((int) $max) + 1,
            ]);
        }

        $club->fill([
            'title' => $row['name'],
            'subtitle' => $created ? $governorate?->name : $club->subtitle,
            'legacy_id' => $row['club_id'],
            'scope' => $scope,
            'institution_name' => $institutionName,
            'institution_type' => $institutionType,
            'governorate_id' => $governorate?->id,
            'email' => $email,
            'facebook_url' => $facebook,
            'founded_on' => $founded,
            'charter_signed' => $row['charte'] === '1',
            'status' => $row['is_active'] === '1' ? 'active' : 'inactive',
        ])->save();

        return ['created' => $created, 'governorate' => $governorate !== null];
    }

    private function fallbackParent(string $scope): OrgNode
    {
        $key = array_search($scope, OrgChartLinker::SYSTEM_SCOPES, true);

        return ($key ? OrgNode::query()->where('system_key', $key)->first() : null)
            ?? OrgNode::query()->whereNull('parent_id')->orderBy('id')->firstOrFail();
    }

    /**
     * Etablissement (universite / lycee) du club : le club legacy porte le nom de son etablissement ;
     * plusieurs clubs d'un meme etablissement (ex. ESPRIT TIC et ESPRIT GCEM) partagent le meme nom.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function institutionOf(array $row, string $scope): array
    {
        $type = OrgScopes::institutionType($scope);
        $name = preg_replace('/\s+/u', ' ', $row['name']);
        $name = self::INSTITUTION_ALIASES[mb_strtolower($name)] ?? $name;

        return $type === null || Str::slug($name) === '' ? [null, null] : [$name, $type];
    }

    private function resolveGovernorate(string $city): ?Governorate
    {
        $slug = Str::slug($city);
        if ($slug === '') {
            return null;
        }

        // Graphies de l'ancienne base et villes rattachees a leur gouvernorat.
        $slug = [
            'bizert' => 'bizerte',
            'mednine' => 'medenine',
            'mannouba' => 'manouba',
            'kassrine' => 'kasserine',
            'kef' => 'le-kef',
            'jandouba' => 'jendouba',
            'kelibia' => 'nabeul',
        ][$slug] ?? $slug;

        return Governorate::query()->where('slug', $slug)->first();
    }

    private function resolveScope(array $row): string
    {
        // SenJun : 3 = club universitaire, 4 = club lycee (fiable, contrairement au nom).
        if ($row['sen_jun'] === '4') {
            return 'high-school';
        }

        if ($row['sen_jun'] === '3') {
            return 'university';
        }

        $haystack = mb_strtolower(trim(($row['name'] ?? '').' '.($row['city'] ?? '').' '.($row['facebook'] ?? '').' '.($row['email'] ?? '')));

        if (str_contains($haystack, 'lycee') || str_contains($haystack, 'lycée') || str_contains($haystack, 'h.lif') || str_contains($haystack, 'high school')) {
            return 'high-school';
        }

        if (str_contains($haystack, 'alumni')) {
            return 'alumni';
        }

        return 'university';
    }

    private function parseRow(string $line): ?array
    {
        $line = rtrim($line, ",\r\n");

        if (! preg_match_all("/'((?:\\\\'|[^'])*)'|-?\\d+|NULL/", $line, $matches)) {
            return null;
        }

        $tokens = $matches[0];
        if (count($tokens) !== 9) {
            return null;
        }

        $values = array_map(function (string $token): string|null {
            if ($token === 'NULL') {
                return null;
            }

            if ($token !== '' && $token[0] === '\'') {
                $value = substr($token, 1, -1);
                return str_replace(["\\'", "\\\\"], ["'", "\\"], $value);
            }

            return $token;
        }, $tokens);

        return [
            'club_id' => (int) $values[0],
            'name' => trim((string) $values[1]),
            'birthday' => trim((string) ($values[2] ?? '')),
            'city' => trim((string) ($values[3] ?? '')),
            'facebook' => trim((string) ($values[4] ?? '')),
            'email' => trim((string) ($values[5] ?? '')),
            'is_active' => trim((string) ($values[6] ?? '')),
            'charte' => trim((string) ($values[7] ?? '')),
            'sen_jun' => trim((string) ($values[8] ?? '')),
        ];
    }
}