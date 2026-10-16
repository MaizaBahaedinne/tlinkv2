<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    public function entities(): JsonResponse
    {
        return response()->json([
            'data' => Entity::query()->orderBy('name')->get(),
        ]);
    }

    public function governorates(): JsonResponse
    {
        return response()->json([
            'data' => Governorate::query()->orderBy('name')->get(),
        ]);
    }
}
