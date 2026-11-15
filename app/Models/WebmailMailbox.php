<?php

namespace App\Models;

use App\Support\MarketingEntities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Boîte e-mail partagée d'une entité. Le mot de passe est généré et gardé par T.Link (chiffré, jamais sérialisé,
 * jamais affiché) : les membres autorisés lisent la boîte depuis le webmail sans jamais le connaître.
 */
class WebmailMailbox extends Model
{
    protected $table = 'webmail_mailboxes';

    protected $fillable = [
        'club_id', 'scope', 'address', 'display_name', 'password', 'signature', 'is_active', 'provisioned', 'last_uid', 'unseen',
        'checked_at', 'last_error', 'created_by',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'encrypted',
        'is_active' => 'boolean',
        'provisioned' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function entityKey(): string
    {
        return MarketingEntities::keyOf($this->club_id, $this->scope);
    }

    public function entityLabel(): string
    {
        return MarketingEntities::label($this->club_id, $this->scope);
    }

    public function title(): string
    {
        return $this->display_name ?: $this->entityLabel();
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'club_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(WebmailContact::class, 'mailbox_id');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(WebmailLabel::class, 'mailbox_id');
    }
}
