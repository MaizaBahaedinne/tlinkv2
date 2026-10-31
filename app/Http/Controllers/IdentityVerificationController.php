<?php

namespace App\Http\Controllers;

use App\Models\IdentityVerification;
use App\Support\IdentityVerifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Demande de « Profil vérifié » : CIN tunisienne (recto, verso) et selfie, validés à la main par un administrateur.
 */
class IdentityVerificationController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->loadMissing('profile');

        return view('profile.verification', [
            'user' => $user,
            'verified' => IdentityVerifications::isVerified($user),
            'latest' => IdentityVerifications::latestFor($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user()->loadMissing('profile');

        if (IdentityVerifications::isVerified($user)) {
            return redirect()->route('profile.verification')->with('status', 'Votre profil est déjà vérifié.');
        }

        if (IdentityVerifications::latestFor($user)?->isPending()) {
            return redirect()->route('profile.verification')->with('status', 'Votre demande est déjà en cours d’examen.');
        }

        $image = ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'];

        $data = $request->validate([
            'id_front' => [...$image, 'dimensions:min_width=600,min_height=400'],
            'id_back' => [...$image, 'dimensions:min_width=600,min_height=400'],
            'selfie' => [...$image, 'dimensions:min_width=400,min_height=400'],
            'consent' => ['accepted'],
        ], [
            'id_front.required' => 'La photo du recto de la carte d’identité est obligatoire.',
            'id_back.required' => 'La photo du verso de la carte d’identité est obligatoire.',
            'selfie.required' => 'Le selfie est obligatoire.',
            '*.image' => 'Le fichier doit être une photo (JPG, PNG ou WebP).',
            '*.mimes' => 'Le fichier doit être une photo (JPG, PNG ou WebP).',
            '*.max' => 'La photo est trop lourde (6 Mo maximum).',
            'id_front.dimensions' => 'La photo du recto est trop petite : prenez-la plus près, bien nette (600 × 400 px minimum).',
            'id_back.dimensions' => 'La photo du verso est trop petite : prenez-la plus près, bien nette (600 × 400 px minimum).',
            'selfie.dimensions' => 'Le selfie est trop petit : prenez-le plus près, bien net (400 × 400 px minimum).',
            'consent.accepted' => 'Vous devez accepter le traitement de vos documents pour la vérification.',
        ]);

        IdentityVerifications::submit($user, [
            'front' => $data['id_front'],
            'back' => $data['id_back'],
            'selfie' => $data['selfie'],
        ]);

        return redirect()->route('profile.verification')->with('status', 'Documents envoyés. Un administrateur va les examiner : vous serez notifié de la décision.');
    }
}
