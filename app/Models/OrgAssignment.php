<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrgAssignment extends Model
{
    protected $fillable = ['node_id', 'person_id', 'starts_at', 'ends_at'];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'node_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(OrgPerson::class, 'person_id');
    }
}
