<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Governorate;
use App\Models\OrgNode;
use App\Support\OrgScopes;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    /**
     * Perimetres de la fondation (anciennes « entites »).
     */
    public function entities(): JsonResponse
    {
        return response()->json([
            'data' => collect(OrgScopes::ALL)->map(fn (string $name, string $slug): array => ['slug' => $slug, 'name' => $name])->values(),
        ]);
    }

    /**
     * Clubs de l'organigramme.
     */
    public function clubs(): JsonResponse
    {
        return response()->json([
            'data' => OrgNode::query()->where('kind', 'club')->where('status', 'active')->orderBy('title')
                ->get(['id', 'title', 'scope', 'governorate_id']),
        ]);
    }

    public function governorates(): JsonResponse
    {
        return response()->json([
            'data' => Governorate::query()->orderBy('name')->get(),
        ]);
    }
}
