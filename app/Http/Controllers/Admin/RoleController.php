<?php

namespace App\Http\Controllers\Admin;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends AdminController
{
    public function index(Request $request): View
    {
        $roles = Role::query()
            ->with('permissions:id,slug')
            ->withCount(['assignments as active_assignments_count' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('id')
            ->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'permissions' => Permission::query()->orderBy('id')->get(['id', 'name', 'slug', 'description']),
        ]);
    }
}
