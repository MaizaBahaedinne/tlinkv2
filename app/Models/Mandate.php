<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mandate extends Model
{
    protected $fillable = [
        'label',
        'start_year',
        'end_year',
        'bureau_name',
        'is_current',
        'notes',
    ];

    protected $casts = [
        'start_year' => 'integer',
        'end_year' => 'integer',
        'is_current' => 'boolean',
    ];
}
