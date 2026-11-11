<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Local (siège, salle, espace partagé…) où les membres pointent leur arrivée et leur départ. */
class Premises extends Model
{
    protected $table = 'premises';

    protected $fillable = ['name', 'address', 'scope', 'node_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'node_id');
    }
}
