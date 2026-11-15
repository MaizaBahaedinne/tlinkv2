<?php

namespace App\Support\Webmail;

/** Rôle d'un dossier (réception, envoyés, brouillons, corbeille, indésirables) deviné d'après son nom. */
final class FolderRoles
{
    public const LABELS = ['inbox' => 'Réception', 'drafts' => 'Brouillons', 'sent' => 'Envoyés', 'trash' => 'Corbeille', 'junk' => 'Indésirables', 'other' => 'Autres'];

    public static function detect(string $name): string
    {
        $name = mb_strtolower($name);

        return match (true) {
            $name === 'inbox' => 'inbox',
            (bool) preg_match('/draft|brouillon/u', $name) => 'drafts',
            (bool) preg_match('/sent|envoy/u', $name) => 'sent',
            (bool) preg_match('/trash|corbeille|deleted|supprim/u', $name) => 'trash',
            (bool) preg_match('/junk|spam|ind[ée]sirable/u', $name) => 'junk',
            default => 'other',
        };
    }

    /**
     * @param array<int, array{role: string, name: string}> $folders
     * @return array<int, array<string, mixed>>
     */
    public static function sort(array $folders): array
    {
        $order = array_flip(array_keys(self::LABELS));

        usort($folders, fn (array $a, array $b): int => [$order[$a['role']] ?? 9, $a['name']] <=> [$order[$b['role']] ?? 9, $b['name']]);

        return $folders;
    }
}
