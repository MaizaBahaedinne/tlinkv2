<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'category',
        'title',
        'subtitle',
        'description',
        'governorate_id',
        'scope',
        'status',
        'legacy_id',
        'email',
        'facebook_url',
        'founded_on',
        'charter_signed',
        'institution_name',
        'institution_type',
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
        'is_collapsed' => 'boolean',
        'charter_signed' => 'boolean',
        'founded_on' => 'date',
        'pos_dx' => 'integer',
        'pos_dy' => 'integer',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
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
}
