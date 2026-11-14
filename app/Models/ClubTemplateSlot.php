<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Poste ou département du modèle d'équipe d'un type de club (University, High School, We Are Carthage). */
class ClubTemplateSlot extends Model
{
    protected $fillable = ['category', 'key', 'parent_key', 'kind', 'title', 'capacity', 'access_level', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];
}
