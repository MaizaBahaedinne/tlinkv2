<?php

namespace App\Http\Controllers\Admin;

use App\Models\MarketingAccessRequest;
use App\Models\User;
use App\Support\DigitalManagers;
use App\Support\EntityPages;
use App\Support\IdentityVerifications;
use App\Support\MarketingEntities;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Demandes d'accès aux comptes marketing. Les postes habilités d'une entité demandent l'accès pour eux-mêmes ou pour
 * un autre membre de l'entité. La demande réunit le compte T.Link du bénéficiaire, son profil Facebook, son téléphone
 * et un profil vérifié ; le digital manager de la Fondation la valide ou la refuse.
 */
class MarketingRequestController extends AdminController
{
    private const MODULE = 'marketing.requests';

    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), MarketingAccessRequest::STATUSES) ? (string) $request->query('status') : null;

        $requests = MarketingEntities::restrict(MarketingAccessRequest::query(), $request->user())
            ->with(['requester:id,name', 'beneficiary:id,name', 'club:id,title'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.marketing.requests.index', [
            'requests' => $requests,
            'status' => $status,
            'canCreate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['create']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        $reach = MarketingEntities::reach($request->user());
        $entity = $reach->has((string) $request->query('entity')) ? (string) $request->query('entity') : null;
        $people = collect();
        $urls = [];

        if ($entity !== null) {
            $found = $reach[$entity];
            $urls = array_filter(EntityPages::urls($found['club_id'], $found['scope']));
            $people = (new EloquentCollection(MarketingEntities::people($entity)->all()))->load('profile:id,user_id,phone,facebook_url')
                ->map(fn (User $person): array => [
                    'id' => $person->id,
                    'label' => $person->name.($person->report_role ? ' — '.$person->report_role : ''),
                    'verified' => IdentityVerifications::isVerified($person),
                    'phone' => (string) $person->profile?->phone,
                    'facebook' => (string) $person->profile?->facebook_url,
                ]);
        }

        return view('admin.marketing.requests.create', [
            'entityOptions' => MarketingEntities::grouped($reach),
            'entity' => $entity,
            'entityLabel' => $entity ? $reach[$entity]['label'] : null,
            'people' => $people,
            'pages' => $urls,
            'me' => $request->user()->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        $reach = MarketingEntities::reach($request->user());

        $data = $request->validate([
            'entity' => ['required', 'string', Rule::in($reach->keys()->all())],
            'beneficiary_id' => ['required', 'integer'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', Rule::in(array_keys(MarketingAccessRequest::PLATFORMS))],
            'facebook_profile_url' => ['required', 'url:http,https', 'max:255'],
            'phone' => ['required', 'string', 'min:6', 'max:40', 'regex:/^[0-9+().\s-]+$/'],
            'reason' => ['nullable', 'string', 'max:1500'],
        ], [
            'entity.in' => 'Cette entité est hors de votre périmètre.',
            'platforms.required' => 'Choisissez au moins une page.',
            'facebook_profile_url.required' => 'Le lien du profil Facebook du bénéficiaire est obligatoire.',
            'phone.required' => 'Le numéro de téléphone du bénéficiaire est obligatoire.',
            'phone.regex' => 'Le numéro de téléphone n’est pas valide.',
        ]);

        $entity = $reach[$data['entity']];
        $beneficiary = MarketingEntities::people($data['entity'])->firstWhere('id', (int) $data['beneficiary_id']);

        if ($beneficiary === null) {
            throw ValidationException::withMessages(['beneficiary_id' => 'Choisissez un membre de cette entité.']);
        }

        if (! IdentityVerifications::isVerified($beneficiary)) {
            throw ValidationException::withMessages(['beneficiary_id' => $beneficiary->name.' n’a pas de profil vérifié : la vérification d’identité est requise pour obtenir un accès.']);
        }

        $available = array_keys(array_filter(EntityPages::urls($entity['club_id'], $entity['scope'])));
        $platforms = array_values(array_unique($data['platforms']));

        if (array_diff($platforms, $available) !== []) {
            throw ValidationException::withMessages(['platforms' => 'Seules les pages renseignées par l’entité peuvent être demandées.']);
        }

        $duplicate = MarketingAccessRequest::query()
            ->where('beneficiary_id', $beneficiary->id)
            ->where('club_id', $entity['club_id'])
            ->when($entity['club_id'] === null, fn ($query) => $query->where('scope', $entity['scope']))
            ->whereIn('status', ['pending', 'approved', 'active'])->get()
            ->contains(fn (MarketingAccessRequest $other): bool => array_intersect((array) $other->platforms, $platforms) !== []);

        if ($duplicate) {
            throw ValidationException::withMessages(['platforms' => 'Une demande est déjà en cours ou active pour ce membre sur cette page.']);
        }

        $accessRequest = MarketingAccessRequest::query()->create([
            'club_id' => $entity['club_id'],
            'scope' => $entity['scope'],
            'requester_id' => $request->user()->id,
            'beneficiary_id' => $beneficiary->id,
            'platforms' => $platforms,
            'reason' => $data['reason'] ?? null,
            'facebook_profile_url' => $data['facebook_profile_url'],
            'phone' => $data['phone'],
            'identity_verified' => true,
            'status' => 'pending',
        ]);

        $this->audit($request, 'admin.marketing.request.created', $accessRequest, $this->trace($accessRequest));

        DigitalManagers::notify(
            array_diff(DigitalManagers::userIds(), [$request->user()->id]),
            'Demande d’accès aux réseaux sociaux',
            $beneficiary->name.' demande l’accès aux pages ('.$accessRequest->platformLabels().') de '.$accessRequest->entityLabel().'.',
            route('admin.marketing.review.show', $accessRequest),
        );

        return redirect()->route('admin.marketing.requests.show', $accessRequest)->with('status', 'Demande envoyée au digital manager de la Fondation.');
    }

    public function show(Request $request, MarketingAccessRequest $accessRequest): View
    {
        $this->authorizeRequest($request, $accessRequest);

        return view('admin.marketing.requests.show', [
            'accessRequest' => $accessRequest->load(['requester:id,name', 'beneficiary:id,name,email', 'reviewer:id,name', 'club:id,title']),
            'canCancel' => $this->canCancel($request, $accessRequest),
        ]);
    }

    /** Annule une demande encore en attente ou validée mais non signée. */
    public function destroy(Request $request, MarketingAccessRequest $accessRequest): RedirectResponse
    {
        $this->authorizeRequest($request, $accessRequest);
        abort_unless($this->canCancel($request, $accessRequest), 403, 'Cette demande ne peut plus être annulée.');

        $accessRequest->update(['status' => 'cancelled']);
        $this->audit($request, 'admin.marketing.request.cancelled', $accessRequest, $this->trace($accessRequest));

        return redirect()->route('admin.marketing.requests.index')->with('status', 'Demande annulée.');
    }

    private function authorizeRequest(Request $request, MarketingAccessRequest $accessRequest): void
    {
        abort_unless(
            $accessRequest->requester_id === $request->user()->id || MarketingEntities::allows($request->user(), $accessRequest->club_id, $accessRequest->scope),
            403,
            'Cette demande est hors de votre périmètre.'
        );
    }

    private function canCancel(Request $request, MarketingAccessRequest $accessRequest): bool
    {
        return in_array($accessRequest->status, ['pending', 'approved'], true)
            && $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['delete']);
    }

    private function trace(MarketingAccessRequest $accessRequest): array
    {
        return ['entity' => $accessRequest->entityKey(), 'beneficiary_id' => $accessRequest->beneficiary_id, 'platforms' => $accessRequest->platforms];
    }
}
