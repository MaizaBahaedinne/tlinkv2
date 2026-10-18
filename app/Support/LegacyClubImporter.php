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
            'skipped' => 0,
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
                continue;
            }

            if (in_array($row['club_id'], [-1, 0, 1, 2], true)) {
                $this->syncBranch($row);
                $stats['branches']++;
                continue;
            }

            $this->syncClub($row);
            $stats['clubs']++;
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

    private function syncClub(array $row): void
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

        $governorate = null;
        if (! empty($row['city'])) {
            $governorate = Governorate::query()
                ->whereRaw('LOWER(slug) = ?', [Str::slug($row['city'])])
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($row['city'])])
                ->first();
        }

        Club::query()->updateOrCreate(
            ['slug' => 'legacy-'.$row['club_id'].'-'.Str::slug($row['name'])],
            [
                'name' => $row['name'],
                'entity_id' => $entity?->id,
                'department_id' => $department?->id,
                'governorate_id' => $governorate?->id,
                'status' => ($row['is_active'] ?? '0') === '1' ? 'active' : 'inactive',
            ]
        );
    }

    private function resolveEntitySlug(array $row): string
    {
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
            'name' => (string) $values[1],
            'birthday' => (string) ($values[2] ?? ''),
            'city' => (string) ($values[3] ?? ''),
            'facebook' => (string) ($values[4] ?? ''),
            'email' => (string) ($values[5] ?? ''),
            'is_active' => (string) ($values[6] ?? ''),
            'charte' => (string) ($values[7] ?? ''),
            'sen_jun' => (string) ($values[8] ?? ''),
        ];
    }
}