<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Department;
use App\Models\Entity;
use App\Models\Governorate;
use Illuminate\Support\Str;

class LegacyClubImporter
{
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
                $this->syncBranch($row);
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

    private function syncBranch(array $row): void
    {
        $branchMap = [
            -1 => ['slug' => 'foundation', 'name' => 'Fondation', 'category' => 'foundation', 'department' => 'Bureau national'],
            0 => ['slug' => 'university', 'name' => 'University', 'category' => 'education', 'department' => 'Bureau universitaire'],
            1 => ['slug' => 'high-school', 'name' => 'High School', 'category' => 'education', 'department' => 'Bureau lycée'],
            2 => ['slug' => 'alumni', 'name' => 'Alumni', 'category' => 'alumni', 'department' => 'Bureau alumni'],
        ];

        $meta = $branchMap[$row['club_id']];

        $entity = Entity::query()->updateOrCreate(
            ['slug' => $meta['slug']],
            [
                'name' => $meta['name'],
                'category' => $meta['category'],
                'description' => 'Import legacy du club '.$meta['name'].' depuis tbl_club.',
                'is_active' => ($row['is_active'] ?? '0') === '1',
            ]
        );

        Department::query()->updateOrCreate(
            [
                'entity_id' => $entity->id,
                'slug' => Str::slug($meta['department']),
            ],
            [
                'name' => $meta['department'],
                'description' => 'Bureau hérité du dump legacy tbl_club.',
            ]
        );
    }

    /** @return array{created: bool, governorate: bool} */
    private function syncClub(array $row): array
    {
        $entitySlug = $this->resolveEntitySlug($row);
        $entity = Entity::query()->where('slug', $entitySlug)->first();

        if (! $entity) {
            $entity = Entity::query()->where('slug', 'university')->first() ?? Entity::query()->first();
        }

        $departmentSlug = match ($entity?->slug) {
            'high-school' => 'bureau-lycee',
            'alumni' => 'bureau-alumni',
            'foundation' => 'bureau-national',
            default => 'bureau-universitaire',
        };

        $department = null;
        if ($entity) {
            $department = Department::query()->where('entity_id', $entity->id)->where('slug', $departmentSlug)->first();
        }

        $governorate = $this->resolveGovernorate($row['city']);

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

        $slug = 'legacy-'.$row['club_id'].'-'.Str::slug($row['name']);
        $club = Club::query()->where('legacy_id', $row['club_id'])->first()
            ?? Club::query()->where('slug', $slug)->first()
            ?? new Club();
        $created = ! $club->exists;

        $club->fill([
            'name' => $row['name'],
            'slug' => $club->slug ?: $slug,
            'legacy_id' => $row['club_id'],
            'entity_id' => $entity?->id,
            'department_id' => $department?->id,
            'governorate_id' => $governorate?->id,
            'email' => $email,
            'facebook_url' => $facebook,
            'founded_on' => $founded,
            'charter_signed' => $row['charte'] === '1',
            'status' => $row['is_active'] === '1' ? 'active' : 'inactive',
        ])->save();

        return ['created' => $created, 'governorate' => $governorate !== null];
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

    private function resolveEntitySlug(array $row): string
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