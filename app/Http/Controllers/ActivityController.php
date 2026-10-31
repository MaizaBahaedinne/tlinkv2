<?php

namespace App\Http\Controllers;

use App\Models\ClubActivity;
use App\Support\Challenges\ActivityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Activités des clubs côté membre : fiche, inscription et validation de présence par QR code. */
class ActivityController extends Controller
{
    public function show(Request $request, ClubActivity $activity): View
    {
        $user = $request->user();
        $this->authorizeView($request, $activity);

        $activity->load(['club:id,title', 'challenge', 'department:id,title', 'organizers.club:id,title']);
        $registration = $activity->registrations()->where('user_id', $user->id)->first();

        return view('challenges.activity', [
            'activity' => $activity,
            'registration' => $registration,
            'active' => $registration !== null && $registration->status !== 'cancelled',
            'reason' => ActivityAccess::ineligibleReason($user, $activity),
            'registered' => $activity->registeredCount(),
            'canManage' => ActivityAccess::canManageActivity($user, $activity),
        ]);
    }

    public function register(Request $request, ClubActivity $activity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($activity->isPublished(), 404);

        if (! $activity->registrationOpen()) {
            return back()->with('error', 'Les inscriptions à cette activité sont closes.');
        }

        if ($reason = ActivityAccess::ineligibleReason($user, $activity)) {
            return back()->with('error', $reason);
        }

        $answers = $this->answers($request, $activity);

        DB::transaction(function () use ($user, $activity, $answers): void {
            $activity = ClubActivity::query()->whereKey($activity->id)->lockForUpdate()->firstOrFail();
            $existing = $activity->registrations()->where('user_id', $user->id)->first();

            if ($existing !== null && $existing->status !== 'cancelled') {
                return;
            }

            $full = $activity->capacity !== null && $activity->registeredCount() >= $activity->capacity;
            $values = ['status' => $full ? 'waitlisted' : 'registered', 'answers' => $answers, 'checked_in_at' => null];

            if ($existing !== null) {
                $existing->update($values);
            } else {
                $activity->registrations()->create($values + ['user_id' => $user->id]);
            }
        });

        $status = $activity->registrations()->where('user_id', $user->id)->value('status');

        return back()->with('status', $status === 'waitlisted' ? 'Activité complète : vous êtes sur liste d’attente.' : 'Inscription enregistrée. Le jour J, scannez le QR code de l’activité pour valider votre présence.');
    }

    public function cancel(Request $request, ClubActivity $activity): RedirectResponse
    {
        $registration = $activity->registrations()->where('user_id', $request->user()->id)->whereIn('status', ['registered', 'waitlisted'])->firstOrFail();

        if ($registration->checked_in_at !== null) {
            return back()->with('error', 'Votre présence est déjà validée.');
        }

        $registration->update(['status' => 'cancelled']);

        // Une place libérée passe à la personne en tête de la liste d'attente.
        if ($activity->capacity !== null && $activity->registeredCount() < $activity->capacity) {
            $activity->registrations()->where('status', 'waitlisted')->orderBy('id')->first()?->update(['status' => 'registered']);
        }

        return back()->with('status', 'Inscription annulée.');
    }

    /** Adresse du QR code affiché par les organisateurs : valide la présence du membre inscrit, pendant la fenêtre de l'activité. */
    public function checkin(Request $request, ClubActivity $activity, string $token): RedirectResponse
    {
        abort_unless(hash_equals($activity->checkin_token, $token) && $activity->status === 'published', 404);

        $registration = $activity->registrations()->where('user_id', $request->user()->id)->where('status', 'registered')->first();

        if ($registration === null) {
            return redirect()->route('activities.show', $activity)->with('error', 'Vous n’êtes pas inscrit à cette activité : inscrivez-vous d’abord, puis scannez à nouveau le QR code.');
        }

        if (! $activity->checkinOpen()) {
            return redirect()->route('activities.show', $activity)->with('error', 'La validation de présence n’est ouverte que de 1 h avant le début à 3 h après la fin de l’activité.');
        }

        $registration->update(['checked_in_at' => $registration->checked_in_at ?? now()]);

        return redirect()->route('activities.show', $activity)->with('status', 'Présence validée. Merci et bonne activité !');
    }

    /** Une activité publiée est visible de tous les membres ; sinon seulement des gestionnaires des clubs organisateurs. */
    private function authorizeView(Request $request, ClubActivity $activity): void
    {
        abort_unless($activity->isPublished() || ActivityAccess::canManageActivity($request->user(), $activity), 404);
    }

    /**
     * Réponses au formulaire d'inscription, contrôlées question par question.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function answers(Request $request, ClubActivity $activity): array
    {
        $given = (array) $request->input('answers', []);
        $answers = [];
        $errors = [];

        foreach ($activity->fields() as $index => $field) {
            $raw = $given[$index] ?? null;
            $value = is_scalar($raw) ? trim((string) $raw) : '';

            if ($field['type'] === 'checkbox') {
                $value = $raw ? 'Oui' : 'Non';

                if (($field['required'] ?? false) && ! $raw) {
                    $errors["answers.$index"] = "« {$field['label']} » doit être coché.";
                }
            } elseif ($value === '') {
                if ($field['required'] ?? false) {
                    $errors["answers.$index"] = "« {$field['label']} » est obligatoire.";
                }
            } elseif ($field['type'] === 'choice' && ! in_array($value, $field['options'] ?? [], true)) {
                $errors["answers.$index"] = "Choix invalide pour « {$field['label']} ».";
            } elseif (mb_strlen($value) > ($field['type'] === 'textarea' ? 1000 : 255)) {
                $errors["answers.$index"] = "Réponse trop longue pour « {$field['label']} ».";
            }

            $answers[] = ['label' => $field['label'], 'value' => $value];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $answers;
    }
}
