<?php

namespace App\Http\Controllers\Admin;

use App\Models\MarketingAccessRequest;
use App\Support\ClubReach;
use App\Support\DigitalManagers;
use App\Support\MarketingCharters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Validation des demandes d'accès aux comptes marketing par le digital manager de la Fondation : validation (suivie
 * de la signature de la charte par le bénéficiaire), refus, attribution du portfolio Meta et retrait de l'accès.
 */
class MarketingReviewController extends AdminController
{
    private const MODULE = 'marketing.review';

    public function index(Request $request): View
    {
        $this->authorizeReviewer($request);

        $status = array_key_exists((string) $request->query('status'), MarketingAccessRequest::STATUSES) ? (string) $request->query('status') : ($request->has('status') ? null : 'pending');

        $requests = MarketingAccessRequest::query()
            ->with(['requester:id,name', 'beneficiary:id,name', 'club:id,title'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.marketing.review.index', [
            'requests' => $requests,
            'status' => $status,
            'pending' => MarketingAccessRequest::query()->where('status', 'pending')->count(),
        ]);
    }

    public function show(Request $request, MarketingAccessRequest $accessRequest): View
    {
        $this->authorizeReviewer($request);

        $accessRequest->load(['requester:id,name', 'beneficiary:id,name,email', 'reviewer:id,name', 'club:id,title']);

        return view('admin.marketing.review.show', [
            'accessRequest' => $accessRequest,
            'charter' => MarketingCharters::forEntity($accessRequest->club_id, $accessRequest->scope),
            'accounts' => $accessRequest->accounts()->get(['id', 'platform', 'name', 'profile_url']),
            'canUpdate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['update']),
        ]);
    }

    public function approve(Request $request, MarketingAccessRequest $accessRequest): RedirectResponse
    {
        $this->authorizeReviewer($request, 'update');
        abort_unless($accessRequest->isPending(), 422, 'Cette demande a déjà été traitée.');

        $data = $request->validate([
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', Rule::in((array) $accessRequest->platforms)],
            'review_note' => ['nullable', 'string', 'max:1500'],
        ], ['platforms.required' => 'Conservez au moins une page.']);

        $accessRequest->update([
            'status' => 'approved',
            'platforms' => array_values(array_unique($data['platforms'])),
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->audit($request, 'admin.marketing.request.approved', $accessRequest, $this->trace($accessRequest));

        DigitalManagers::notify(
            array_unique([$accessRequest->beneficiary_id, $accessRequest->requester_id]),
            'Demande d’accès validée : signez la charte',
            'Votre accès aux pages ('.$accessRequest->platformLabels().') de '.$accessRequest->entityLabel().' est validé. Signez la charte d’usage des réseaux sociaux pour l’obtenir.',
            route('marketing-access.index'),
        );

        return redirect()->route('admin.marketing.review.show', $accessRequest)->with('status', 'Demande validée : le bénéficiaire doit signer la charte.');
    }

    public function refuse(Request $request, MarketingAccessRequest $accessRequest): RedirectResponse
    {
        $this->authorizeReviewer($request, 'update');
        abort_unless($accessRequest->isPending(), 422, 'Cette demande a déjà été traitée.');

        $data = $request->validate(['review_note' => ['required', 'string', 'max:1500']], ['review_note.required' => 'Indiquez le motif du refus.']);

        $accessRequest->update(['status' => 'refused', 'review_note' => $data['review_note'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $this->audit($request, 'admin.marketing.request.refused', $accessRequest, $this->trace($accessRequest));

        DigitalManagers::notify(
            array_unique([$accessRequest->beneficiary_id, $accessRequest->requester_id]),
            'Demande d’accès refusée',
            'Votre demande d’accès aux pages de '.$accessRequest->entityLabel().' est refusée : '.$data['review_note'],
            route('marketing-access.index'),
        );

        return redirect()->route('admin.marketing.review.index')->with('status', 'Demande refusée.');
    }

    /** Attribue (ou met à jour) le portfolio Meta du bénéficiaire. */
    public function meta(Request $request, MarketingAccessRequest $accessRequest): RedirectResponse
    {
        $this->authorizeReviewer($request, 'update');
        abort_unless(in_array($accessRequest->status, ['approved', 'active'], true), 422, 'Le portfolio Meta s’attribue à une demande validée.');

        $data = $request->validate(['meta_portfolio' => ['required', 'string', 'max:190']], ['meta_portfolio.required' => 'Indiquez le portfolio Meta attribué.']);

        $accessRequest->update(['meta_portfolio' => $data['meta_portfolio'], 'meta_assigned_at' => now(), 'meta_assigned_by' => $request->user()->id]);
        $this->audit($request, 'admin.marketing.request.meta', $accessRequest, $this->trace($accessRequest) + ['meta_portfolio' => $data['meta_portfolio']]);

        DigitalManagers::notify([$accessRequest->beneficiary_id], 'Portfolio Meta attribué', 'Le portfolio Meta « '.$data['meta_portfolio'].' » vous est attribué pour '.$accessRequest->entityLabel().'.', route('marketing-access.index'));

        return redirect()->route('admin.marketing.review.show', $accessRequest)->with('status', 'Portfolio Meta attribué.');
    }

    public function revoke(Request $request, MarketingAccessRequest $accessRequest): RedirectResponse
    {
        $this->authorizeReviewer($request, 'update');
        abort_unless(in_array($accessRequest->status, ['approved', 'active'], true), 422, 'Seul un accès validé ou actif peut être retiré.');

        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:1500']]);

        $accessRequest->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by' => $request->user()->id, 'review_note' => $data['review_note'] ?? $accessRequest->review_note]);
        $this->audit($request, 'admin.marketing.request.revoked', $accessRequest, $this->trace($accessRequest));

        DigitalManagers::notify([$accessRequest->beneficiary_id], 'Accès aux réseaux sociaux retiré', 'Votre accès aux pages de '.$accessRequest->entityLabel().' a été retiré.', route('marketing-access.index'));

        return redirect()->route('admin.marketing.review.show', $accessRequest)->with('status', 'Accès retiré. Pensez à changer les mots de passe concernés.');
    }

    /** Réservé aux personnes à portée globale (digital manager de la Fondation, administrateurs). */
    private function authorizeReviewer(Request $request, string $action = 'read'): void
    {
        $this->requireModuleAction($request, self::MODULE, $action);
        abort_unless(ClubReach::isGlobal($request->user()), 403, 'La validation des demandes revient au digital manager de la Fondation.');
    }

    private function trace(MarketingAccessRequest $accessRequest): array
    {
        return ['entity' => $accessRequest->entityKey(), 'beneficiary_id' => $accessRequest->beneficiary_id, 'platforms' => $accessRequest->platforms];
    }
}
