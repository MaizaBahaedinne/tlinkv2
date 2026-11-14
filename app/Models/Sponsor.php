<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sponsor ou partenaire (organisation) du carnet d'adresses partagé de la Fondation et de ses clubs. */
class Sponsor extends Model
{
    public const RELATIONS = ['sponsor' => 'Sponsor', 'partner' => 'Partenaire', 'both' => 'Sponsor et partenaire'];

    public const STATUSES = [
        'prospect' => 'À contacter',
        'contacted' => 'Contacté',
        'discussing' => 'En discussion',
        'active' => 'Actif (on travaille ensemble)',
        'declined' => 'A décliné',
        'former' => 'Ancien',
    ];

    protected $fillable = ['name', 'relation', 'status', 'sector', 'website', 'email', 'phone', 'address', 'city', 'notes', 'owner_club_id', 'created_by'];

    public function contacts(): HasMany
    {
        return $this->hasMany(SponsorContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(SponsorLead::class)->latest('id');
    }

    public function ownerClub(): BelongsTo
    {
        return $this->belongsTo(OrgNode::class, 'owner_club_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
