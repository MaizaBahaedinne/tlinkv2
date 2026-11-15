<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Compte autorisé à la main sur une boîte e-mail : ses droits (lire, écrire, classer, supprimer). */
class WebmailMailboxUser extends Model
{
    protected $table = 'webmail_mailbox_users';

    protected $fillable = ['mailbox_id', 'user_id', 'actions', 'granted_by'];

    protected $casts = ['actions' => 'array'];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(WebmailMailbox::class, 'mailbox_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
