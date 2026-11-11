<?php

namespace App\Http\Controllers\Admin;

use App\Models\MemberCard;
use App\Support\Cards\MemberCards;
use App\Support\MembershipResponsibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Cartes de membre du périmètre : suivi, suspension et réactivation. L'émission et l'expiration sont automatiques. */
class MemberCardController extends AdminController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(MemberCard::STATUSES))],
        ]);

        $cards = $this->visible($request)
            ->with(['user:id,name,email', 'membership.node:id,title', 'mandate:id,label'])
            ->withCount('scans')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $like = '%'.addcslashes($needle, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('number', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.cards.index', [
            'cards' => $cards,
            'filters' => $filters,
            'statuses' => MemberCard::STATUSES,
            'counts' => $this->visible($request)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'mandate' => MemberCards::currentMandate(),
        ]);
    }

    public function suspend(Request $request, MemberCard $card): RedirectResponse
    {
        abort_unless($this->visible($request)->whereKey($card->id)->exists(), 403, 'Cette carte est hors de votre périmètre.');

        $card->update(['status' => MemberCard::STATUS_SUSPENDED]);
        $this->audit($request, 'admin.card.suspended', $card, ['number' => $card->number, 'user_id' => $card->user_id]);

        return back()->with('status', 'Carte suspendue : elle ne permet plus de pointer.');
    }

    public function reactivate(Request $request, MemberCard $card): RedirectResponse
    {
        abort_unless($this->visible($request)->whereKey($card->id)->exists(), 403, 'Cette carte est hors de votre périmètre.');

        $card->update(['status' => MemberCard::STATUS_ACTIVE]);
        $this->audit($request, 'admin.card.reactivated', $card, ['number' => $card->number, 'user_id' => $card->user_id]);

        // La synchronisation rétablit l'état réel (une carte dont l'adhésion n'est plus valide repasse en « expirée »).
        MemberCards::sync($card->user_id);

        return back()->with('status', 'Carte réactivée.');
    }

    /** @return \Illuminate\Database\Eloquent\Builder<MemberCard> */
    private function visible(Request $request)
    {
        $responsibility = MembershipResponsibility::of($request->user(), 'membership.manage');

        return MemberCard::query()->whereHas('membership', fn ($membership) => $responsibility->apply($membership, 'scope', 'node_id'));
    }
}
