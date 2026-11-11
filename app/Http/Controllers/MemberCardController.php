<?php

namespace App\Http\Controllers;

use App\Models\MemberCard;
use App\Support\Cards\CardCode;
use App\Support\Cards\MemberCards;
use App\Support\OrgScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « Ma carte » : la ou les cartes de membre (une par adhésion validée du mandat en cours) et leur QR code rotatif. */
class MemberCardController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        MemberCards::sync($user->id);

        $cards = MemberCard::query()->where('user_id', $user->id)
            ->with(['membership.node:id,title', 'mandate:id,label,start_year'])
            ->get()
            ->sortBy(fn (MemberCard $card): array => [$card->isValid() ? 0 : 1, -((int) $card->mandate?->start_year), $card->id])
            ->values();

        return view('cards.show', [
            'cards' => $cards,
            'mandate' => MemberCards::currentMandate(),
            'scopeLabels' => OrgScopes::all(),
            'statuses' => MemberCard::STATUSES,
        ]);
    }

    /** Code à afficher en QR : il change toutes les 30 s, la page le redemande tant qu'elle est ouverte. */
    public function code(Request $request, MemberCard $card): JsonResponse
    {
        abort_unless($card->user_id === $request->user()->id, 404);

        if (! $card->isValid()) {
            return response()->json(['valid' => false, 'message' => $card->status === MemberCard::STATUS_SUSPENDED ? 'Carte suspendue.' : 'Carte expirée.'], 200)
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->json([
            'valid' => true,
            'code' => CardCode::payload($card),
            'refresh_in' => CardCode::STEP - (time() % CardCode::STEP),
        ])->header('Cache-Control', 'no-store, private');
    }
}
