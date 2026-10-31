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
            'cin_number' => ['required', 'regex:/^\d{8}$/'],
            'card_issued_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1990-01-01'],
            'card_issued_place' => ['required', 'string', 'min:2', 'max:100'],
            'consent' => ['accepted'],
        ], [
            'cin_number.required' => 'Le numéro de la carte d’identité est obligatoire.',
            'cin_number.regex' => 'Le numéro de la carte d’identité tunisienne comporte exactement 8 chiffres.',
            'card_issued_on.required' => 'La date de délivrance de la carte est obligatoire.',
            'card_issued_on.date_format' => 'La date de délivrance n’est pas valide.',
            'card_issued_on.before_or_equal' => 'La date de délivrance ne peut pas être dans le futur.',
            'card_issued_on.after_or_equal' => 'La date de délivrance n’est pas valide.',
            'card_issued_place.required' => 'La ville de délivrance de la carte est obligatoire.',
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
        ], [
            'cin_number' => $data['cin_number'],
            'card_issued_on' => $data['card_issued_on'],
            'card_issued_place' => trim($data['card_issued_place']),
        ]);

        return redirect()->route('profile.verification')->with('status', 'Documents envoyés. Un administrateur va les examiner : vous serez notifié de la décision.');
    }
}
