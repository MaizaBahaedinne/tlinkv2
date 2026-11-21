<?php

namespace App\Support\Events;

use App\Models\EventRegistrationImport;

/**
 * Lit une liste copiée depuis un tableur : une personne par ligne, colonnes Nom, Prénom, Téléphone
 * (séparées par une tabulation, un point-virgule ou une barre verticale ; à défaut, le numéro est repéré en fin de ligne).
 */
final class RegistrationImportParser
{
    /**
     * @return array{rows: list<array{last_name: string, first_name: string, phone: string, phone_key: string}>, invalid: list<string>, duplicates: int}
     */
    public static function parse(string $text): array
    {
        $rows = [];
        $invalid = [];
        $duplicates = 0;
        $seen = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parsed = self::parseLine($line);

            if ($parsed === null) {
                // L'en-tête du tableur (« Nom Prénom Téléphone ») n'est pas une erreur.
                if (! preg_match('/^(nom|name)\b.*(t[ée]l|phone)/iu', $line)) {
                    $invalid[] = $line;
                }

                continue;
            }

            if (isset($seen[$parsed['phone_key']])) {
                $duplicates++;

                continue;
            }

            $seen[$parsed['phone_key']] = true;
            $rows[] = $parsed;
        }

        return ['rows' => $rows, 'invalid' => $invalid, 'duplicates' => $duplicates];
    }

    /**
     * @return array{last_name: string, first_name: string, phone: string, phone_key: string}|null
     */
    private static function parseLine(string $line): ?array
    {
        $columns = null;

        foreach (["\t", ';', '|'] as $separator) {
            if (str_contains($line, $separator)) {
                $columns = array_values(array_filter(array_map('trim', explode($separator, $line)), fn (string $part): bool => $part !== ''));

                break;
            }
        }

        if ($columns === null || count($columns) < 3) {
            // Sans séparateur : le téléphone est le dernier bloc de chiffres, le premier mot est le nom, le reste le prénom.
            if (! preg_match('/^(.+?)\s+(\+?[\d][\d\s().\-]{6,})$/u', $line, $match)) {
                return null;
            }

            $words = preg_split('/\s+/u', trim($match[1]), 2) ?: [];

            if (count($words) < 2) {
                return null;
            }

            $columns = [$words[0], $words[1], trim($match[2])];
        }

        $phone = array_pop($columns);
        $key = EventRegistrationImport::phoneKey($phone);

        if ($key === null || count($columns) < 2) {
            return null;
        }

        $last = self::name($columns[0]);
        $first = self::name(implode(' ', array_slice($columns, 1)));

        if ($last === '' || $first === '') {
            return null;
        }

        return ['last_name' => $last, 'first_name' => $first, 'phone' => trim(preg_replace('/\s+/u', ' ', $phone) ?? $phone), 'phone_key' => $key];
    }

    private static function name(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return mb_convert_case(mb_strtolower($value), MB_CASE_TITLE);
    }
}
