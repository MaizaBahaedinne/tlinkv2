<?php

namespace App\Http\Controllers\Admin;

use App\Models\ScopeValidator;
use App\Models\User;
use App\Support\OrgScopes;
use App\Support\ScopeValidators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Validateurs désignés par entité : ils reçoivent les demandes d'adhésion de leur entité et peuvent les traiter.
 */
class ScopeValidatorController extends AdminController
{
    public function store(Request $request, string $scope): RedirectResponse
    {
        abort_unless(OrgScopes::valid($scope), 404);

        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']], [
            'user_id.required' => 'Choisissez un validateur.',
        ]);

        // Seuls les comptes qui détiennent déjà le droit de valider pour cette entité peuvent être désignés.
        if (! in_array((int) $data['user_id'], ScopeValidators::candidates($scope), true)) {
            return back()->with('error', 'Ce compte n’a pas le droit de valider les demandes de cette entité (droit « Valider ou rejeter les demandes » sur un poste).');
        }

        $validator = ScopeValidator::query()->firstOrCreate(['scope' => $scope, 'user_id' => (int) $data['user_id']]);

        if ($validator->wasRecentlyCreated) {
            $this->audit($request, 'admin.scope_validator.added', $validator, ['scope' => $scope, 'user_id' => $validator->user_id]);
        }

        return back()->with('status', 'Validateur ajouté à '.OrgScopes::label($scope).'.');
    }

    public function destroy(Request $request, string $scope, User $user): RedirectResponse
    {
        abort_unless(OrgScopes::valid($scope), 404);

        $validator = ScopeValidator::query()->where('scope', $scope)->where('user_id', $user->id)->first();

        if ($validator !== null) {
            $validator->delete();
            $this->audit($request, 'admin.scope_validator.removed', $validator, ['scope' => $scope, 'user_id' => $user->id]);
        }

        return back()->with('status', 'Validateur retiré de '.OrgScopes::label($scope).'.');
    }
}
