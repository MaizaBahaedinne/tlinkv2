<?php

namespace App\Http\Controllers\Admin;

use App\Models\OrgNode;
use App\Models\Premises;
use App\Support\MembershipResponsibility;
use App\Support\OrgAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Locaux où les membres pointent. Chacun appartient à un périmètre (et éventuellement à un club). */
class PremisesController extends AdminController
{
    public function index(Request $request): View
    {
        $premises = MembershipResponsibility::of($request->user(), 'event.manage')
            ->apply(Premises::query(), 'scope', 'node_id')
            ->with('node:id,title')
            ->orderByDesc('is_active')->orderBy('name')
            ->get();

        return view('admin.premises.index', [
            'premises' => $premises,
            'scopes' => $this->scopeOptions($request, 'event.manage'),
            'canBeGlobal' => $this->context($request)->isGlobal('event.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $premises = Premises::query()->create($this->validated($request) + ['is_active' => true]);

        $this->audit($request, 'admin.premises.created', $premises, ['name' => $premises->name]);

        return back()->with('status', "Local « {$premises->name} » ajouté.");
    }

    public function update(Request $request, Premises $premises): RedirectResponse
    {
        $this->authorizePremises($request, $premises);

        $premises->update($request->validate(['is_active' => ['required', 'boolean']]));
        $this->audit($request, 'admin.premises.updated', $premises, ['name' => $premises->name, 'is_active' => $premises->is_active]);

        return back()->with('status', $premises->is_active ? 'Local réactivé.' : 'Local désactivé : il n’est plus proposé au pointage.');
    }

    public function destroy(Request $request, Premises $premises): RedirectResponse
    {
        $this->authorizePremises($request, $premises);

        $name = $premises->name;
        $this->audit($request, 'admin.premises.deleted', $premises, ['name' => $name]);
        $premises->delete();

        return back()->with('status', "Local « {$name} » supprimé.");
    }

    private function authorizePremises(Request $request, Premises $premises): void
    {
        abort_unless(
            MembershipResponsibility::of($request->user(), 'event.manage')->covers($premises->scope, $premises->node_id),
            403,
            'Ce local est hors de votre périmètre.'
        );
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $responsibility = OrgAccess::responsibility($request->user(), 'event.manage');

        // Un responsable de club (sans périmètre entier) crée des locaux rattachés à son club.
        if (! $responsibility['all'] && $responsibility['scopes'] === [] && $responsibility['clubs'] !== []) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'address' => ['nullable', 'string', 'max:255'],
            ]);
            $club = OrgNode::query()->findOrFail($responsibility['clubs'][0]);

            return $data + ['node_id' => $club->id, 'scope' => OrgAccess::scopeOf($club)];
        }

        $global = $this->context($request)->isGlobal('event.manage');

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'scope' => [$global ? 'nullable' : 'required', Rule::in(array_keys($this->scopeOptions($request, 'event.manage')))],
        ]);
    }
}
