<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Support\Gamification\GamificationEngine;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Badges du membre : ceux obtenus, et pour chacun ses missions et son avancement.
 */
class BadgeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Rattrape l'activité passée avant d'afficher l'avancement.
        GamificationEngine::refreshSafely($user->id);

        $badges = collect(GamificationEngine::progress($user->id))
            ->filter(fn (array $row): bool => $row['earned'] || $row['total'] > 0)
            ->values();

        return view('badges.index', [
            'user' => $user,
            'tiers' => Badge::TIERS,
            'badges' => $badges,
            'earnedCount' => $badges->where('earned', true)->count(),
        ]);
    }
}
