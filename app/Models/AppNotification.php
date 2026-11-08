<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Notification push (applications mobiles) : sans effet tant que Firebase n'est pas configuré.
        static::created(fn (AppNotification $notification) => \App\Support\Push\NotificationPush::notify($notification));
    }
}
