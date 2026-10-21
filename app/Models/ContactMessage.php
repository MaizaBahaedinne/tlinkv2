<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    public const STATUSES = [
        'new' => 'Nouveau',
        'read' => 'Lu',
        'archived' => 'Archivé',
    ];

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'locale',
        'status',
        'ip_address',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
