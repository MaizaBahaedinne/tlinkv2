<?php

use App\Models\Membership;
use App\Models\OrgNode;
use App\Support\PositionMemberships;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Une adhésion rattachée à un poste (position_id) affichait « Membre » : elle porte désormais le titre du poste.
     * Seul le champ membership_type est modifié, et seulement quand il valait encore « member ».
     */
    public function up(): void
    {
        $positions = OrgNode::query()->whereIn('id', Membership::query()->where('membership_type', 'member')->whereNotNull('position_id')->select('position_id'))
            ->get(['id', 'title'])->keyBy('id');

        Membership::query()
            ->where('membership_type', 'member')
            ->whereNotNull('position_id')
            ->orderBy('id')
            ->each(function (Membership $membership) use ($positions): void {
                $role = PositionMemberships::roleOf($positions[$membership->position_id] ?? null);

                if ($role !== 'member') {
                    // Mise à jour directe : ni observateurs ni dates modifiés.
                    Membership::query()->whereKey($membership->id)->toBase()->update(['membership_type' => $role]);
                }
            });
    }

    public function down(): void
    {
        // Le rôle d'origine (« member ») n'est pas conservé : rien à restaurer.
    }
};
