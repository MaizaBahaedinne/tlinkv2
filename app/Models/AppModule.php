<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Module (ou sous-module) fonctionnel de l'application T.Link.
 * Chaque module déclare les actions CRUD qu'il supporte : c'est la base des habilitations par poste.
 */
class AppModule extends Model
{
    public const ACTIONS = [
        'create' => 'Créer',
        'read' => 'Consulter',
        'update' => 'Modifier',
        'delete' => 'Supprimer',
    ];

    /** Lettres affichées dans les badges (C R U D). */
    public const ACTION_LETTERS = ['create' => 'C', 'read' => 'R', 'update' => 'U', 'delete' => 'D'];

    protected $fillable = ['parent_id', 'key', 'name', 'description', 'actions', 'is_active', 'is_system', 'sort_order'];

    protected $casts = [
        'actions' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
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

    /**
     * Actions CRUD supportées, dans l'ordre C R U D.
     *
     * @return array<int, string>
     */
    public function supportedActions(): array
    {
        return array_values(array_intersect(array_keys(self::ACTIONS), $this->actions ?? []));
    }
}
