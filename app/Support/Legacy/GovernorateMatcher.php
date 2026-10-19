<?php

namespace App\Support\Legacy;

use App\Models\Governorate;
use Illuminate\Support\Str;

/** Retrouve un gouvernorat a partir des graphies de l'ancienne base (avec cache memoire). */
final class GovernorateMatcher
{
    private const ALIASES = [
        'bizert' => 'bizerte',
        'mednine' => 'medenine',
        'mannouba' => 'manouba',
        'kassrine' => 'kasserine',
        'kef' => 'le-kef',
        'jandouba' => 'jendouba',
        'kelibia' => 'nabeul',
        'marsa' => 'tunis',
    ];

    /** @var array<string, Governorate>|null */
    private ?array $bySlug = null;

    public function find(?string $name): ?Governorate
    {
        $slug = Str::slug(trim((string) $name));
        if ($slug === '') {
            return null;
        }

        $this->bySlug ??= Governorate::query()->get()->keyBy('slug')->all();

        return $this->bySlug[self::ALIASES[$slug] ?? $slug] ?? null;
    }
}
