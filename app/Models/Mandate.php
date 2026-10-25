<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mandate extends Model
{
    protected $fillable = [
        'label',
        'start_year',
        'end_year',
        'bureau_name',
        'root_node_id',
        'is_current',
        'notes',
    ];

    protected $casts = [
        'start_year' => 'integer',
        'end_year' => 'integer',
        'root_node_id' => 'integer',
        'is_current' => 'boolean',
    ];

    public function rootNode(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'root_node_id');
    }
}
