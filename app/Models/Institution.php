<?php

namespace App\Models;

use App\Support\ProfileOrganizations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Établissement d'enseignement (lycée, université, faculté, école) auquel sont rattachés des clubs
 * et que les membres citent dans leur formation.
 */
class Institution extends Model
{
    public const TYPES = [
        'university' => 'Université',
        'faculty' => 'Faculté / école supérieure',
        'high_school' => 'Lycée',
        'other' => 'Autre établissement',
    ];

    protected $fillable = ['name', 'slug', 'type', 'parent_id', 'governorate_id', 'address', 'website_url', 'email', 'phone', 'description', 'logo_path', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        // Les clubs gardent le nom et le type de leur établissement : on les met à jour quand la fiche change.
        static::saved(function (self $institution): void {
            if ($institution->wasChanged(['name', 'type'])) {
                OrgNode::query()->where('institution_id', $institution->id)->update([
                    'institution_name' => $institution->name,
                    'institution_type' => $institution->clubType(),
                ]);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(OrgNode::class, 'institution_id')->where('kind', 'club')->orderBy('title');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? self::TYPES['other'];
    }

    /** Type porté par la fiche d'un club (université, lycée, autre) : une faculté est une université pour les clubs. */
    public function clubType(): string
    {
        return $this->type === 'faculty' ? 'university' : $this->type;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** Lien uniquement en http(s) (évite les schémas dangereux type javascript:). */
    public function safeUrl(): ?string
    {
        return $this->website_url && preg_match('#^https?://#i', $this->website_url) === 1 ? $this->website_url : null;
    }

    /** « Université · Tunis » : informations courtes affichées sur les profils. */
    public function summary(): string
    {
        return collect([
            $this->typeLabel(),
            $this->parent?->name,
            $this->governorate?->name,
        ])->filter()->implode(' · ');
    }

    /** Établissement portant ce nom (sans tenir compte de la casse) ; créé s'il n'existe pas. */
    public static function resolve(string $name, ?string $type = null, ?int $governorateId = null): ?self
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $slug = ProfileOrganizations::key($name);

        if ($slug === '') {
            return null;
        }

        return self::query()->firstOrCreate(['slug' => $slug], [
            'name' => Str::limit($name, 160, ''),
            'type' => array_key_exists((string) $type, self::TYPES) ? $type : 'other',
            'governorate_id' => $governorateId,
        ]);
    }

    /** Crée la fiche de chaque établissement cité par les clubs et y rattache ces clubs. */
    public static function backfillFromClubs(): void
    {
        OrgNode::query()
            ->where('kind', 'club')
            ->whereNull('institution_id')
            ->whereNotNull('institution_name')
            ->where('institution_name', '!=', '')
            ->chunkById(200, function ($clubs): void {
                foreach ($clubs as $club) {
                    $institution = self::resolve($club->institution_name, $club->institution_type, $club->governorate_id);

                    if ($institution === null) {
                        continue;
                    }

                    // Le logo déjà téléversé sur le club devient celui de l'établissement (copie : chaque fichier a un seul propriétaire).
                    if ($institution->logo_path === null && $club->logo_path && Storage::disk('public')->exists($club->logo_path)) {
                        $copy = 'institutions/logos/'.Str::random(8).'-'.basename($club->logo_path);
                        Storage::disk('public')->copy($club->logo_path, $copy);
                        $institution->update(['logo_path' => $copy]);
                    }

                    OrgNode::query()->whereKey($club->id)->update(['institution_id' => $institution->id, 'institution_name' => $institution->name]);
                }
            });
    }
}
