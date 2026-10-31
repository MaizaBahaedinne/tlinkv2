<?php

namespace App\Http\Controllers\Admin;

use App\Models\IdentityVerification;
use App\Support\IdentityVerifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Examen manuel des demandes « Profil vérifié » (CIN tunisienne recto/verso + selfie).
 * Les pièces sont chiffrées, servies uniquement ici, et supprimées dès la décision.
 */
class IdentityVerificationController extends AdminController
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $status = in_array($status, array_keys(IdentityVerification::STATUSES), true) ? $status : 'pending';

        $counts = IdentityVerification::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        $verifications = IdentityVerification::query()
            ->with('user:id,name,email')
            ->where('status', $status)
            ->orderBy($status === 'pending' ? 'created_at' : 'reviewed_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.verifications.index', [
            'verifications' => $verifications,
            'status' => $status,
            'counts' => $counts,
            'statuses' => IdentityVerification::STATUSES,
        ]);
    }

    public function show(Request $request, IdentityVerification $verification): View
    {
        $verification->load(['user:id,name,email', 'reviewer:id,name']);

        if ($verification->isPending()) {
            $this->audit($request, 'admin.verification.viewed', $verification, ['user_id' => $verification->user_id]);
        }

        return view('admin.verifications.show', ['verification' => $verification]);
    }

    /** Affiche une pièce déchiffrée : jamais mise en cache, jamais exposée par une URL publique. */
    public function document(IdentityVerification $verification, string $document): Response
    {
        $file = IdentityVerifications::read($verification, $document);

        abort_if($file === null, 404);

        return response($file[0], 200, [
            'Content-Type' => $file[1],
            'Content-Disposition' => 'inline; filename="document.'.($file[1] === 'image/png' ? 'png' : ($file[1] === 'image/webp' ? 'webp' : 'jpg')).'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function approve(Request $request, IdentityVerification $verification): RedirectResponse
    {
        if (! $verification->isPending()) {
            return redirect()->route('admin.verifications.index')->with('status', 'Cette demande a déjà été traitée.');
        }

        IdentityVerifications::approve($verification, $request->user());
        $this->audit($request, 'admin.verification.approved', $verification, ['user_id' => $verification->user_id]);

        return redirect()->route('admin.verifications.index')->with('status', 'Profil vérifié. Les documents ont été supprimés.');
    }

    public function reject(Request $request, IdentityVerification $verification): RedirectResponse
    {
        if (! $verification->isPending()) {
            return redirect()->route('admin.verifications.index')->with('status', 'Cette demande a déjà été traitée.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], ['reason.required' => 'Indiquez le motif du refus : il sera communiqué au membre.']);

        IdentityVerifications::reject($verification, $request->user(), $data['reason']);
        $this->audit($request, 'admin.verification.rejected', $verification, ['user_id' => $verification->user_id, 'reason' => $data['reason']]);

        return redirect()->route('admin.verifications.index')->with('status', 'Demande refusée. Les documents ont été supprimés.');
    }
}
