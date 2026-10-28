<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrgNode extends Model
{
    public const CATEGORIES = [
        'presidency' => 'Présidence',
        'direction' => 'Direction exécutive',
        'national_office' => 'Bureaux nationaux',
        'project' => 'Projets & réseaux',
        'regional_club' => 'Clubs régionaux We Are',
        'university_club' => 'University Clubs',
        'highschool_club' => 'High School Clubs',
    ];

    public const KINDS = [
        'position' => 'Poste',
        'unit' => 'Unité / branche',
        'department' => 'Département de bureau national',
        'club' => 'Club',
        'club_department' => 'Département de club',
    ];

    /** Types de blocs qu'un utilisateur peut creer a la main (le club suit la fiche Club). */
    public const MANUAL_KINDS = ['position', 'unit', 'department', 'club_department'];

    public const CLUB_STATUSES = ['active' => 'Actif', 'inactive' => 'Inactif', 'archived' => 'Archivé', 'draft' => 'Brouillon'];

    public const INSTITUTION_TYPES = ['university' => 'Université', 'high_school' => 'Lycée', 'other' => 'Autre'];

    public const CAPACITIES = ['single' => 'Un seul titulaire', 'multiple' => 'Plusieurs personnes'];

    public const RELATIONS = ['hierarchy' => 'Lien hiérarchique', 'attachment' => 'Rattachement (sans lien hiérarchique)'];

    public const VALIDATIONS = ['confirmed' => 'Confirmé', 'to_validate' => 'À valider'];

    public const LAYOUTS = ['horizontal' => 'Horizontale', 'vertical' => 'Verticale (liste)'];

    protected $fillable = [
        'parent_id',
        'relation_type',
        'kind',
        'capacity',
        'access_level',
        'permissions',
        'module_permissions',
        'category',
        'title',
        'subtitle',
        'description',
        'governorate_id',
        'scope',
        'extra_scopes',
        'status',
        'legacy_id',
        'email',
        'facebook_url',
        'founded_on',
        'charter_signed',
        'institution_name',
        'institution_type',
        'institution_id',
        'logo_path',
        'validation_status',
        'validation_note',
        'children_layout',
        'is_collapsed',
        'pos_dx',
        'pos_dy',
        'sort_order',
        'system_key',
        'role_key',
    ];

    protected $casts = [
        'permissions' => 'array',
        'module_permissions' => 'array',
        'extra_scopes' => 'array',
        'is_collapsed' => 'boolean',
        'charter_signed' => 'boolean',
        'founded_on' => 'date',
        'pos_dx' => 'integer',
        'pos_dy' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $hidden = ['join_token'];

    protected static function booted(): void
    {
        // Chaque poste a son lien d'inscription direct (jeton non devinable, hors $fillable).
        static::saving(function (self $node): void {
            if ($node->kind === 'position' && blank($node->join_token)) {
                $node->join_token = Str::random(40);
            }
        });

        // Les droits coches n'ont de sens que pour le niveau « personnalise » : on ne garde pas de restes.
        static::saving(function (self $node): void {
            if ($node->isDirty('access_level') && $node->access_level !== 'custom') {
                $node->permissions = null;
            }
        });

        static::saving(function (self $node): void {
            $node->syncInstitution();
        });
    }

    /**
     * Garde le club et sa fiche d'établissement cohérents : choisir un établissement recopie son nom/type ;
     * saisir un nom (import, installation) retrouve ou crée la fiche correspondante.
     */
    protected function syncInstitution(): void
    {
        if ($this->kind !== 'club' || ! Schema::hasTable('institutions') || ! Schema::hasColumn('org_nodes', 'institution_id')) {
            return;
        }

        $idChanged = $this->isDirty('institution_id');
        $nameChanged = $this->isDirty('institution_name');

        if ($idChanged && $this->institution_id !== null) {
            $institution = Institution::query()->find($this->institution_id);

            if ($institution === null) {
                $this->institution_id = null;
            } else {
                $this->institution_name = $institution->name;
                $this->institution_type = $institution->clubType();

                return;
            }
        }

        if ($idChanged && ! $nameChanged) {
            $this->institution_name = null;
            $this->institution_type = null;

            return;
        }

        if ($nameChanged) {
            $institution = filled($this->institution_name)
                ? Institution::resolve((string) $this->institution_name, $this->institution_type, $this->governorate_id)
                : null;

            $this->institution_id = $institution?->id;
            $this->institution_name = $institution?->name;
            $this->institution_type = $institution?->clubType();
        }
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Logo du club ; à défaut celui de son établissement (si la relation est chargée). */
    public function logoUrl(): ?string
    {
        if ($this->logo_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo_path);
        }

        return $this->relationLoaded('institution') ? $this->institution?->logoUrl() : null;
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrgAssignment::class, 'node_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(PositionApplication::class, 'node_id');
    }

    public function joinUrl(): ?string
    {
        return $this->kind === 'position' && $this->join_token ? route('position.join', $this->join_token) : null;
    }
}
