<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\MyOffice;
use App\Support\OrgChart\OrgChartRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * « Mon bureau » : supérieurs et équipe du membre, et gestion des titulaires des postes situés sous les siens.
 */
class MyOfficeController extends Controller
{
    public function __construct(private readonly OrgChartRules $rules)
    {
    }

    public function index(Request $request): View
    {
        abort_unless(MyOffice::enabled(), 404);

        $user = $request->user();
        $term = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $searchNode = (int) $request->query('node', 0);
        $results = [];

        if ($searchNode > 0 && mb_strlen($term) >= 2) {
            $target = OrgNode::query()->find($searchNode);

            if ($target !== null && MyOffice::canManage($user, $target)) {
                $query = User::query()->where('is_active', true);

                foreach (array_slice(preg_split('/\s+/u', $term), 0, 4) as $word) {
                    $like = '%'.addcslashes($word, '\\%_').'%';
                    $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
                }

                $results = $query->orderBy('name')->limit(8)->get(['id', 'name', 'email'])
                    ->map(fn (User $found): array => [
                        'id' => $found->id,
                        'name' => $found->name,
                        'email' => str_ends_with((string) $found->email, '@legacy.invalid') ? null : $found->email,
                    ])->all();
            }
        }

        return view('office.index', [
            'user' => $user,
            'offices' => MyOffice::overview($user),
            'searchNode' => $searchNode,
            'term' => $term,
            'results' => $results,
            'canAdmin' => \App\Support\AdminMenu::hasAccess(\App\Support\AdminContext::of($request)),
        ]);
    }

    public function assign(Request $request, OrgNode $node): RedirectResponse
    {
        $this->authorizeManagement($request, $node);

        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $member = User::query()->findOrFail($data['user_id']);

        if (! $member->is_active) {
            throw ValidationException::withMessages(['user_id' => 'Ce compte est désactivé.']);
        }

        $already = OrgAssignment::query()->where('node_id', $node->id)
            ->whereHas('person', fn ($query) => $query->where('user_id', $member->id))->exists();

        if (! $already) {
            $this->rules->assertCanHold($node, $this->rules->currentPeopleCount($node) + 1);

            $person = OrgPerson::query()->firstOrCreate(['user_id' => $member->id], [
                'full_name' => $member->name,
                'contact_visible' => true,
            ]);

            OrgAssignment::query()->firstOrCreate(['node_id' => $node->id, 'person_id' => $person->id]);

            AppNotification::query()->create([
                'user_id' => $member->id,
                'type' => 'office.assigned',
                'title' => 'Nouveau poste : « '.$node->title.' »',
                'body' => $request->user()->name.' vous a affecté(e) à ce poste.',
                'data' => ['node_id' => $node->id],
            ]);

            AuditLogger::record($request->user()->id, 'office.holder.assigned', OrgNode::class, $node->id, ['title' => $node->title, 'user_id' => $member->id], $request->ip());
        }

        return redirect()->route('office.index')->with('status', $member->name.' est affecté(e) à « '.$node->title.' ».');
    }

    public function unassign(Request $request, OrgNode $node, OrgPerson $person): RedirectResponse
    {
        $this->authorizeManagement($request, $node);

        $removed = OrgAssignment::query()->where('node_id', $node->id)->where('person_id', $person->id)->delete();

        if ($removed > 0) {
            AuditLogger::record($request->user()->id, 'office.holder.removed', OrgNode::class, $node->id, ['title' => $node->title, 'person_id' => $person->id], $request->ip());
        }

        return redirect()->route('office.index')->with('status', 'Titulaire retiré de « '.$node->title.' ».');
    }

    private function authorizeManagement(Request $request, OrgNode $node): void
    {
        abort_unless(MyOffice::enabled(), 404);
        abort_unless(MyOffice::canManage($request->user(), $node), 403, 'Ce poste ne se situe pas sous le vôtre.');
    }
}
