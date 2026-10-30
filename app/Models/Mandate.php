<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mandate extends Model
{
    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_CURRENT = 'current';

    public const STATUS_CLOSED = 'closed';

    /** Statuts de gestion d'un mandat (libellé affiché). */
    public const STATUSES = [
        self::STATUS_UPCOMING => 'À venir',
        self::STATUS_CURRENT => 'En cours',
        self::STATUS_CLOSED => 'Clôturé',
    ];

    protected $fillable = [
        'label',
        'start_year',
        'end_year',
        'bureau_name',
        'root_node_id',
        'is_current',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_year' => 'integer',
        'end_year' => 'integer',
        'root_node_id' => 'integer',
        'is_current' => 'boolean',
    ];

    /** `is_current` et `status` restent toujours cohérents : « en cours » ⇔ statut `current`. */
    protected static function booted(): void
    {
        static::saving(function (Mandate $mandate): void {
            if ($mandate->isDirty('status')) {
                $mandate->is_current = $mandate->status === self::STATUS_CURRENT;
            } elseif ($mandate->isDirty('is_current')) {
                $mandate->status = $mandate->is_current
                    ? self::STATUS_CURRENT
                    : (in_array($mandate->status, [self::STATUS_UPCOMING, self::STATUS_CLOSED], true) ? $mandate->status : self::STATUS_CLOSED);
            }
        });
    }

    public function rootNode(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'root_node_id');
    }
}
