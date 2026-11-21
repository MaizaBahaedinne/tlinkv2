<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Accompagnant déclaré par un inscrit (High School) : nom, prénom et lien familial. */
class EventRegistrationCompanion extends Model
{
    public const RELATIONSHIPS = [
        'father' => 'Père',
        'mother' => 'Mère',
        'brother' => 'Frère',
        'sister' => 'Sœur',
        'grandparent' => 'Grand-parent',
        'uncle_aunt' => 'Oncle / Tante',
        'guardian' => 'Tuteur légal',
        'other' => 'Autre',
    ];

    protected $fillable = ['registration_id', 'first_name', 'last_name', 'relationship'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }

    public function relationshipLabel(): string
    {
        return self::RELATIONSHIPS[$this->relationship] ?? $this->relationship;
    }
}
