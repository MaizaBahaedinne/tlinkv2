<?php

namespace App\Support\Legacy;

/**
 * Lecteur en flux des instructions `INSERT INTO` d'un dump mysqldump.
 * Gere les chaines multi-lignes, les echappements (\', \\, \n, '') et NULL.
 */
final class SqlDumpReader
{
    /**
     * @return \Generator<int, array<string, string|null>> lignes indexees par nom de colonne
     */
    public static function rows(string $path, string $table): \Generator
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Dump legacy introuvable: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir le dump legacy: {$path}");
        }

        $header = 'INSERT INTO `'.$table.'` (';

        try {
            while (($line = fgets($handle)) !== false) {
                if (! str_starts_with($line, $header)) {
                    continue;
                }

                $end = strpos($line, ') VALUES');
                if ($end === false) {
                    continue;
                }

                $columns = array_map(
                    static fn (string $column): string => trim($column, " `\r\n"),
                    explode(',', substr($line, strlen($header), $end - strlen($header)))
                );

                $statement = substr($line, $end + strlen(') VALUES'));
                $inString = self::endsInsideString($statement, false);

                while (! ($inString === false && str_ends_with(rtrim($statement), ';'))) {
                    $next = fgets($handle);
                    if ($next === false) {
                        break;
                    }
                    $inString = self::endsInsideString($next, $inString);
                    $statement .= $next;
                }

                foreach (self::tuples($statement) as $values) {
                    if (count($values) !== count($columns)) {
                        continue;
                    }
                    yield array_combine($columns, $values);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /** Indique si, apres ce fragment, on est a l'interieur d'une chaine SQL. */
    private static function endsInsideString(string $chunk, bool $inString): bool
    {
        $length = strlen($chunk);
        for ($i = 0; $i < $length; $i++) {
            $char = $chunk[$i];
            if ($inString && $char === '\\') {
                $i++;
            } elseif ($char === "'") {
                $inString = ! $inString;
            }
        }

        return $inString;
    }

    /** @return \Generator<int, array<int, string|null>> */
    private static function tuples(string $body): \Generator
    {
        $length = strlen($body);
        $i = 0;

        while ($i < $length) {
            if ($body[$i] !== '(') {
                $i++;
                continue;
            }

            $i++;
            $values = [];

            while ($i < $length) {
                while ($i < $length && ctype_space($body[$i])) {
                    $i++;
                }

                if ($body[$i] === "'") {
                    [$value, $i] = self::readString($body, $i + 1, $length);
                    $values[] = $value;
                } else {
                    $start = $i;
                    while ($i < $length && $body[$i] !== ',' && $body[$i] !== ')') {
                        $i++;
                    }
                    $token = trim(substr($body, $start, $i - $start));
                    $values[] = strcasecmp($token, 'NULL') === 0 ? null : $token;
                }

                while ($i < $length && ctype_space($body[$i])) {
                    $i++;
                }

                if ($i < $length && $body[$i] === ',') {
                    $i++;
                    continue;
                }

                $i++; // ')'
                break;
            }

            yield $values;
        }
    }

    /** @return array{0: string, 1: int} */
    private static function readString(string $body, int $i, int $length): array
    {
        $value = '';

        while ($i < $length) {
            $char = $body[$i];

            if ($char === '\\' && $i + 1 < $length) {
                $next = $body[$i + 1];
                $value .= match ($next) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    '0' => "\0",
                    'Z' => "\x1a",
                    'b' => "\x08",
                    default => $next,
                };
                $i += 2;
                continue;
            }

            if ($char === "'") {
                if (($body[$i + 1] ?? '') === "'") {
                    $value .= "'";
                    $i += 2;
                    continue;
                }

                return [$value, $i + 1];
            }

            $value .= $char;
            $i++;
        }

        return [$value, $i];
    }
}
