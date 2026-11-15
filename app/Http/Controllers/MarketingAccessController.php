<?php

namespace App\Http\Controllers;

use App\Models\MarketingAccessRequest;
use App\Support\AuditLogger;
use App\Support\DigitalManagers;
use App\Support\MarketingCharters;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Accès aux réseaux sociaux côté membre : suivi de ses demandes, signature de la charte d'usage une fois la demande
 * validée, puis affichage des mots de passe (chaque consultation est tracée) et du portfolio Meta attribué.
 */
class MarketingAccessController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $requests = MarketingAccessRequest::query()
            ->with(['requester:id,name', 'beneficiary:id,name', 'club:id,title'])
            ->where(fn ($query) => $query->where('beneficiary_id', $user->id)->orWhere('requester_id', $user->id))
            ->latest('id')->get();

        return view('marketing-access.index', ['requests' => $requests, 'me' => $user]);
    }

    public function charter(Request $request, MarketingAccessRequest $accessRequest): View
    {
        $this->authorizeBeneficiary($request, $accessRequest);

        $signed = $accessRequest->charter_signed_at !== null;
        $charter = $signed
            ? ['title' => 'Charte d’usage des réseaux sociaux signée', 'body' => (string) $accessRequest->charter_text, 'version' => $accessRequest->charter_version]
            : MarketingCharters::forEntity($accessRequest->club_id, $accessRequest->scope);

        return view('marketing-access.charter', ['accessRequest' => $accessRequest, 'charter' => $charter, 'signed' => $signed]);
    }

    public function sign(Request $request, MarketingAccessRequest $accessRequest): RedirectResponse
    {
        $this->authorizeBeneficiary($request, $accessRequest);
        abort_unless($accessRequest->status === 'approved', 422, 'Cette demande n’attend pas de signature.');

        $data = $request->validate([
            'accept' => ['accepted'],
            'signed_name' => ['required', 'string', 'min:3', 'max:160'],
        ], ['accept.accepted' => 'Vous devez accepter la charte pour obtenir l’accès.', 'signed_name.required' => 'Saisissez votre nom complet pour signer.']);

        $charter = MarketingCharters::forEntity($accessRequest->club_id, $accessRequest->scope);

        $accessRequest->update([
            'status' => 'active',
            'charter_version' => $charter['version'],
            'charter_text' => $charter['title']."\n\n".$charter['body'],
            'charter_signed_name' => $data['signed_name'],
            'charter_signed_ip' => $request->ip(),
            'charter_signed_at' => now(),
        ]);

        AuditLogger::record(
            actorId: $request->user()->id,
            action: 'marketing.access.charter_signed',
            targetType: $accessRequest::class,
            targetId: $accessRequest->id,
            metadata: ['entity' => $accessRequest->entityKey(), 'charter_version' => $charter['version']],
            ip: $request->ip(),
        );

        DigitalManagers::notify(
            array_diff(DigitalManagers::userIds(), [$request->user()->id]),
            'Charte signée : portfolio Meta à attribuer',
            $request->user()->name.' a signé la charte de '.$accessRequest->entityLabel().'. Attribuez-lui un portfolio Meta.',
            route('admin.marketing.review.show', $accessRequest),
        );

        return redirect()->route('marketing-access.index')->with('status', 'Charte signée : vous pouvez maintenant consulter les accès.');
    }

    /** Mots de passe des pages demandées (JSON, jamais mis en cache) : réservés au bénéficiaire, après signature de la charte. */
    public function secrets(Request $request, MarketingAccessRequest $accessRequest): JsonResponse
    {
        $this->authorizeBeneficiary($request, $accessRequest);
        abort_unless($accessRequest->isActive(), 403, 'Signez d’abord la charte pour accéder aux mots de passe.');

        try {
            $accounts = $accessRequest->accounts()->get()->map(fn ($account): array => [
                'platform' => $account->platformLabel(),
                'name' => $account->name,
                'profile_url' => $account->profile_url,
                'login' => $account->login,
                'password' => $account->password,
            ])->values();
        } catch (DecryptException) {
            return response()->json(['message' => 'Un mot de passe ne peut pas être déchiffré : contactez le digital manager.'], 422);
        }

        AuditLogger::record(
            actorId: $request->user()->id,
            action: 'marketing.access.revealed',
            targetType: $accessRequest::class,
            targetId: $accessRequest->id,
            metadata: ['entity' => $accessRequest->entityKey(), 'platforms' => $accessRequest->platforms],
            ip: $request->ip(),
        );

        return response()->json(['accounts' => $accounts, 'meta_portfolio' => $accessRequest->meta_portfolio])->header('Cache-Control', 'no-store, private');
    }

    private function authorizeBeneficiary(Request $request, MarketingAccessRequest $accessRequest): void
    {
        abort_unless($accessRequest->beneficiary_id === $request->user()->id, 403, 'Cet accès est personnel.');
    }
}
