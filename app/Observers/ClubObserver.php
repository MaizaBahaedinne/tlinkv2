<?php

namespace App\Observers;

use App\Models\Club;
use App\Support\OrgChart\OrgChartLinker;

/**
 * Garde l'organigramme coherent avec les clubs : un club cree ou modifie dans l'administration,
 * par un import ou depuis l'organigramme est toujours represente par un bloc.
 */
class ClubObserver
{
    public function __construct(private readonly OrgChartLinker $linker)
    {
    }

    public function created(Club $club): void
    {
        if ($this->linker->isActive()) {
            $this->linker->ensureClubNode($club);
        }
    }

    public function updated(Club $club): void
    {
        if ($club->wasChanged(['name', 'entity_id', 'department_id', 'institution_id', 'governorate_id']) && $this->linker->isActive()) {
            $this->linker->syncFromClub($club);
        }
    }
}
