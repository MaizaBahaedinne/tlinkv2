<?php

namespace App\Http\Controllers\Admin;

use App\Models\AppNotification;
use App\Models\OrgAssignment;
use App\Models\OrgPerson;
use App\Models\PositionApplication;
use App\Support\AdminContext;
use App\Support\OrgChart\OrgChartRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Candidatures reçues via les liens d'inscription des postes. Accepter une candidature affecte la personne au poste
 * (et donc lui donne les droits du poste) : réservé aux administrateurs globaux, comme toute modification de structure.
 */
class PositionApplicationController extends AdminController
{
    public function __construct(private readonly OrgChartRules $rules)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in([...array_keys(PositionApplication::STATUSES), 'all'])]]);
        $status = $filters['status'] ?? 'pending';

        $counts = PositionApplication::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $applications = PositionApplication::query()
            ->with(['node:id,title,capacity', 'user.profile', 'decider:id,name'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.structure.applications', [
            'applications' => $applications,
            'statuses' => PositionApplication::STATUSES,
            'status' => $status,
            'counts' => $counts,
            'canDecide' => AdminContext::of($request)->isGlobal('entity.manage'),
        ]);
    }

    public function decide(Request $request, PositionApplication $application): RedirectResponse
    {
        abort_unless(AdminContext::of($request)->isGlobal('entity.manage'), 403, 'Seuls les administrateurs globaux peuvent décider des candidatures.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($application, $data, $request): void {
            $application = PositionApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($application->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Cette candidature a déjà été traitée.']);
            }

            $node = $application->node()->firstOrFail();
            $user = $application->user()->firstOrFail();

            if ($data['decision'] === 'approved') {
                if (! $user->is_active) {
                    throw ValidationException::withMessages(['decision' => 'Ce compte est désactivé.']);
                }

                $already = OrgAssignment::query()->where('node_id', $node->id)
                    ->whereHas('person', fn ($query) => $query->where('user_id', $user->id))->exists();

                if (! $already) {
                    $this->rules->assertCanHold($node, $this->rules->currentPeopleCount($node) + 1);

                    $person = OrgPerson::query()->firstOrCreate(['user_id' => $user->id], [
                        'full_name' => $user->name,
                        'contact_visible' => true,
                    ]);

                    OrgAssignment::query()->firstOrCreate(['node_id' => $node->id, 'person_id' => $person->id]);
                }
            }

            $application->update([
                'status' => $data['decision'],
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'decision_note' => $data['note'] ?? null,
            ]);

            $this->audit($request, $data['decision'] === 'approved' ? 'orgchart.application.approved' : 'orgchart.application.rejected', $application, [
                'position' => $node->title,
                'user_id' => $user->id,
            ]);

            AppNotification::query()->create([
                'user_id' => $user->id,
                'type' => 'position.application.decided',
                'title' => 'Candidature au poste « '.$node->title.' »',
                'body' => $data['decision'] === 'approved'
                    ? 'Votre candidature a été acceptée : vous occupez désormais ce poste.'
                    : 'Votre candidature n’a pas été retenue.'.(($data['note'] ?? null) ? ' '.$data['note'] : ''),
                'data' => ['application_id' => $application->id, 'decision' => $data['decision']],
            ]);
        });

        return back()->with('status', $data['decision'] === 'approved' ? 'Candidature acceptée : la personne est affectée au poste.' : 'Candidature refusée.');
    }
}
