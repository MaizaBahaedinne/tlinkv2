<?php

namespace App\Http\Controllers\Admin;

use App\Models\ActivityReport;
use App\Models\ActivityReportItem;
use App\Models\AppNotification;
use App\Models\ClubActivity;
use App\Support\Challenges\ActivityAccess;
use App\Support\OrgAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Compte rendu de clôture d'une activité : le club le rédige (RH, marketing, événements et sponsoring),
 * le président le valide, puis le comité d'évaluation du défi le reçoit.
 */
class ActivityReportController extends AdminController
{
    private const IMAGE_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

    public function show(Request $request, ClubActivity $activity): View|RedirectResponse
    {
        $this->authorizeActivity($request, $activity);
        $report = $activity->report;

        if ($report === null && ! $activity->isFinished()) {
            return redirect()->route('admin.activities.show', $activity)->with('error', 'Le compte rendu se rédige une fois l’activité terminée.');
        }

        $activity->loadMissing(['club:id,title', 'challenge:id,title']);
        $report?->load(['members.user:id,name', 'items', 'partners', 'submitter:id,name', 'validator:id,name']);
        $report ??= new ActivityReport(['status' => 'draft']);
        $editable = $report->isEditable();

        return view('admin.activities.report', [
            'activity' => $activity,
            'report' => $report,
            'editable' => $editable,
            'candidates' => $editable ? ActivityAccess::reportCandidates($activity) : collect(),
            'canValidate' => $report->status === 'pending' && ActivityAccess::canValidateReport($request->user(), $activity),
            'memberRows' => $this->memberRows($report),
            'partnerRows' => $this->partnerRows($report),
            'mediaRows' => $this->mediaRows($report),
        ]);
    }

    /** Enregistre le brouillon, ou le soumet au président du club. */
    public function update(Request $request, ClubActivity $activity): RedirectResponse
    {
        $this->authorizeActivity($request, $activity);
        abort_unless($activity->isFinished(), 422, 'L’activité n’est pas terminée.');

        $report = $activity->report;

        if ($report !== null && ! $report->isEditable()) {
            return back()->with('error', 'Ce compte rendu n’est plus modifiable : il est en attente de validation ou déjà validé.');
        }

        $data = $request->validate($this->rules());
        $submit = $data['action'] === 'submit';
        $members = $this->members((array) ($data['members'] ?? []), $activity);
        $partners = $this->partners((array) ($data['partners'] ?? []));
        $media = $this->media((array) ($data['media'] ?? []));

        $report ??= new ActivityReport(['activity_id' => $activity->id, 'status' => 'draft']);
        $existing = $report->exists ? $report->items()->get() : collect();
        $removed = collect((array) ($data['remove_files'] ?? []))->map(fn ($id): int => (int) $id);
        $keptProofs = $existing->where('kind', 'proof')->reject(fn (ActivityReportItem $item): bool => $removed->contains($item->id))->count();
        $newProofs = count($request->file('proofs', []));

        if ($keptProofs + $newProofs > ActivityReport::MAX_PROOFS) {
            throw ValidationException::withMessages(['proofs' => 'Une activité accepte au plus '.ActivityReport::MAX_PROOFS.' preuves en photo.']);
        }

        if ($submit) {
            $this->assertReadyToSubmit($data, $members, $keptProofs + $newProofs);
        }

        DB::transaction(function () use ($request, $activity, $report, $data, $submit, $members, $partners, $media, $existing, $removed): void {
            $report->fill([
                'summary' => $data['summary'] ?? null,
                'hr_summary' => $data['hr_summary'] ?? null,
                'budget_planned' => $data['budget_planned'] ?? null,
                'budget_spent' => $data['budget_spent'] ?? null,
                'budget_income' => $data['budget_income'] ?? null,
                'budget_notes' => $data['budget_notes'] ?? null,
                'status' => $submit ? 'pending' : 'draft',
            ]);

            if ($submit) {
                $report->fill(['submitted_at' => now(), 'submitted_by' => $request->user()->id, 'president_comment' => null]);
            }

            $report->save();

            $report->members()->delete();
            $report->members()->createMany($members);
            $report->partners()->delete();
            $report->partners()->createMany($partners);
            $report->items()->whereIn('kind', array_keys(ActivityReport::MEDIA_KINDS))->delete();
            $report->items()->createMany($media);

            foreach ($existing->whereIn('id', $removed->all())->whereIn('kind', ['poster', 'proof']) as $item) {
                $this->deleteFile($item);
                $item->delete();
            }

            $directory = 'activity-reports/'.$report->id;

            if ($request->hasFile('poster')) {
                foreach ($report->items()->where('kind', 'poster')->get() as $old) {
                    $this->deleteFile($old);
                    $old->delete();
                }

                $report->items()->create(['kind' => 'poster', 'path' => $request->file('poster')->store($directory, 'public')]);
            }

            foreach ((array) $request->file('proofs', []) as $proof) {
                $report->items()->create(['kind' => 'proof', 'path' => $proof->store($directory, 'public')]);
            }

            if ($submit) {
                $this->notify(
                    ActivityAccess::presidentIds((int) $activity->club_id),
                    'activity.report.submitted',
                    'Compte rendu à valider',
                    "Le compte rendu de « {$activity->title} » attend votre validation avant d’être transmis au comité.",
                    $activity,
                    $request->user()->id,
                );
            }
        });

        $this->audit($request, $submit ? 'admin.activity.report_submitted' : 'admin.activity.report_saved', $activity, ['title' => $activity->title]);

        return redirect()->route('admin.activities.report', $activity)->with('status', $submit
            ? 'Compte rendu soumis au président du club. Il sera transmis au comité une fois validé.'
            : 'Brouillon enregistré.');
    }

    /** Le président valide : le compte rendu est transmis au comité d'évaluation. */
    public function approve(Request $request, ClubActivity $activity): RedirectResponse
    {
        $report = $this->pendingReport($request, $activity);

        $report->update(['status' => 'validated', 'validated_at' => now(), 'validated_by' => $request->user()->id, 'president_comment' => null]);

        $activity->loadMissing('challenge.committee:id');
        $this->notify($activity->challenge->committee->pluck('id')->all(), 'activity.report.validated', 'Compte rendu à évaluer', "Le compte rendu de « {$activity->title} » est validé : l’activité peut être évaluée.", $activity, $request->user()->id, route('challenges.evaluation', $activity->challenge, false));
        $this->notify(array_filter([$report->submitted_by]), 'activity.report.validated', 'Compte rendu validé', "Le président a validé le compte rendu de « {$activity->title} » : il est transmis au comité.", $activity, $request->user()->id);
        $this->audit($request, 'admin.activity.report_validated', $activity, ['title' => $activity->title]);

        return back()->with('status', 'Compte rendu validé et transmis au comité d’évaluation.');
    }

    /** Le président renvoie le compte rendu au club avec un commentaire. */
    public function sendBack(Request $request, ClubActivity $activity): RedirectResponse
    {
        $report = $this->pendingReport($request, $activity);
        $data = $request->validate(['president_comment' => ['required', 'string', 'max:2000']]);

        $report->update(['status' => 'draft', 'president_comment' => $data['president_comment']]);

        $this->notify(
            array_unique(array_merge(array_filter([$report->submitted_by]), OrgAccess::userIdsInClub(ActivityAccess::PERMISSION, (int) $activity->club_id, $request->user()->id))),
            'activity.report.returned',
            'Compte rendu renvoyé',
            "Le président demande des corrections sur le compte rendu de « {$activity->title} ».",
            $activity,
            $request->user()->id,
        );
        $this->audit($request, 'admin.activity.report_returned', $activity, ['title' => $activity->title]);

        return back()->with('status', 'Compte rendu renvoyé au club pour correction.');
    }

    private function pendingReport(Request $request, ClubActivity $activity): ActivityReport
    {
        $this->authorizeActivity($request, $activity);
        abort_unless(ActivityAccess::canValidateReport($request->user(), $activity), 403, 'Seul le président du club porteur valide le compte rendu.');

        $report = $activity->report;
        abort_unless($report !== null && $report->status === 'pending', 422, 'Ce compte rendu n’est pas en attente de validation.');

        return $report;
    }

    private function authorizeActivity(Request $request, ClubActivity $activity): void
    {
        abort_unless(ActivityAccess::canManageActivity($request->user(), $activity), 403, 'Cette activité n’est pas celle de votre club.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['draft', 'submit'])],
            'summary' => ['nullable', 'string', 'max:5000'],
            'hr_summary' => ['nullable', 'string', 'max:3000'],
            'budget_planned' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'budget_spent' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'budget_income' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'budget_notes' => ['nullable', 'string', 'max:3000'],
            'members' => ['nullable', 'array', 'max:200'],
            'members.*.user_id' => ['nullable', 'integer'],
            'members.*.role' => ['nullable', 'string', 'max:120'],
            'members.*.implication' => ['nullable', 'integer', 'between:1,5'],
            'members.*.comment' => ['nullable', 'string', 'max:400'],
            'partners' => ['nullable', 'array', 'max:50'],
            'partners.*.name' => ['nullable', 'string', 'max:160'],
            'partners.*.nature' => ['nullable', Rule::in(array_keys(ActivityReport::NATURES))],
            'partners.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'partners.*.description' => ['nullable', 'string', 'max:400'],
            'media' => ['nullable', 'array', 'max:30'],
            'media.*.kind' => ['nullable', Rule::in(array_keys(ActivityReport::MEDIA_KINDS))],
            'media.*.label' => ['nullable', 'string', 'max:160'],
            'media.*.url' => ['nullable', 'url:http,https', 'max:500'],
            'poster' => ['nullable', ...self::IMAGE_RULES],
            'proofs' => ['nullable', 'array', 'max:'.ActivityReport::MAX_PROOFS],
            'proofs.*' => [...self::IMAGE_RULES],
            'remove_files' => ['nullable', 'array'],
            'remove_files.*' => ['integer'],
        ];
    }

    /**
     * Partie RH : une ligne par membre, notée de 1 à 5 ; seuls les membres des clubs organisateurs sont acceptés.
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function members(array $rows, ClubActivity $activity): array
    {
        $allowed = ActivityAccess::reportCandidates($activity)->pluck('id')->all();
        $members = [];

        foreach ($rows as $index => $row) {
            $userId = (int) ($row['user_id'] ?? 0);

            if ($userId === 0) {
                continue;
            }

            if (! in_array($userId, $allowed, true)) {
                throw ValidationException::withMessages(["members.$index.user_id" => 'Ce membre n’appartient pas aux clubs organisateurs.']);
            }

            if (empty($row['implication'])) {
                throw ValidationException::withMessages(["members.$index.implication" => 'Notez l’implication de ce membre.']);
            }

            $members[$userId] = [
                'user_id' => $userId,
                'role' => filled($row['role'] ?? null) ? trim((string) $row['role']) : null,
                'implication' => (int) $row['implication'],
                'comment' => filled($row['comment'] ?? null) ? trim((string) $row['comment']) : null,
            ];
        }

        return array_values($members);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function partners(array $rows): array
    {
        $partners = [];

        foreach ($rows as $row) {
            if (trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }

            $partners[] = [
                'name' => trim((string) $row['name']),
                'nature' => $row['nature'] ?? 'other',
                'amount' => isset($row['amount']) && $row['amount'] !== '' ? (float) $row['amount'] : null,
                'description' => filled($row['description'] ?? null) ? trim((string) $row['description']) : null,
            ];
        }

        return $partners;
    }

    /**
     * Retombées médias : un lien est exigé pour Facebook et Instagram, un lien ou une précision pour la TV, la radio et la presse.
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function media(array $rows): array
    {
        $items = [];

        foreach ($rows as $index => $row) {
            $kind = $row['kind'] ?? null;
            $label = filled($row['label'] ?? null) ? trim((string) $row['label']) : null;
            $url = filled($row['url'] ?? null) ? trim((string) $row['url']) : null;

            if ($kind === null || $kind === '' || ($label === null && $url === null)) {
                continue;
            }

            if (in_array($kind, ['facebook', 'instagram'], true) && $url === null) {
                throw ValidationException::withMessages(["media.$index.url" => 'Indiquez le lien de la publication.']);
            }

            $items[] = ['kind' => $kind, 'label' => $label, 'url' => $url];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $members
     */
    private function assertReadyToSubmit(array $data, array $members, int $proofs): void
    {
        $errors = [];

        if (blank($data['summary'] ?? null)) {
            $errors['summary'] = 'Rédigez le bilan de l’activité avant de la soumettre.';
        }

        if ($members === []) {
            $errors['members'] = 'Partie RH : notez l’implication d’au moins un membre participant.';
        }

        if ($proofs === 0) {
            $errors['proofs'] = 'Partie marketing : ajoutez au moins une preuve de l’activité (photo).';
        }

        if (! isset($data['budget_spent']) || $data['budget_spent'] === '') {
            $errors['budget_spent'] = 'Partie budget : indiquez les dépenses réelles (0 si aucune).';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function deleteFile(ActivityReportItem $item): void
    {
        if ($item->path !== null) {
            Storage::disk('public')->delete($item->path);
        }
    }

    /** @param  array<int, int|null>  $userIds */
    private function notify(array $userIds, string $type, string $title, string $body, ClubActivity $activity, int $exceptUserId, ?string $url = null): void
    {
        foreach (array_unique(array_filter(array_map('intval', $userIds))) as $userId) {
            if ($userId === $exceptUserId) {
                continue;
            }

            AppNotification::query()->create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => ['activity_id' => $activity->id, 'url' => $url ?? route('admin.activities.report', $activity, false)],
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function memberRows(ActivityReport $report): array
    {
        $rows = old('members', $report->members->map(fn ($member) => ['user_id' => $member->user_id, 'role' => $member->role, 'implication' => $member->implication, 'comment' => $member->comment])->all());

        return array_values((array) $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function partnerRows(ActivityReport $report): array
    {
        $rows = old('partners', $report->partners->map(fn ($partner) => ['name' => $partner->name, 'nature' => $partner->nature, 'amount' => $partner->amount, 'description' => $partner->description])->all());

        return array_values((array) $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function mediaRows(ActivityReport $report): array
    {
        $rows = old('media', $report->mediaLinks()->map(fn ($item) => ['kind' => $item->kind, 'label' => $item->label, 'url' => $item->url])->all());

        return array_values((array) $rows);
    }
}
