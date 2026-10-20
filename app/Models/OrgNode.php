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

    public const KINDS = ['position' => 'Poste', 'unit' => 'Unité / branche', 'club' => 'Club'];

    public const RELATIONS = ['hierarchy' => 'Lien hiérarchique', 'attachment' => 'Rattachement (sans lien hiérarchique)'];

    public const VALIDATIONS = ['confirmed' => 'Confirmé', 'to_validate' => 'À valider'];

    public const LAYOUTS = ['horizontal' => 'Horizontale', 'vertical' => 'Verticale (liste)'];

    protected $fillable = [
        'parent_id',
        'relation_type',
        'kind',
        'category',
        'title',
        'subtitle',
        'description',
        'club_id',
        'governorate_id',
        'entity_id',
        'department_id',
        'institution_id',
        'validation_status',
        'validation_note',
        'children_layout',
        'is_collapsed',
        'pos_dx',
        'pos_dy',
        'sort_order',
        'system_key',
    ];

    protected $casts = [
        'is_collapsed' => 'boolean',
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

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrgAssignment::class, 'node_id');
    }
}
