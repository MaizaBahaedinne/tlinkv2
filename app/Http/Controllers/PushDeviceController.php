<?php

namespace App\Http\Controllers;

use App\Models\PushDevice;
use App\Support\Domains;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enregistrement des appareils pour les notifications push (appelé par les applications mobiles).
 */
class PushDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:255'],
            'platform' => ['required', 'in:android,ios'],
        ]);

        // Un jeton appartient à l'installation : si un autre membre se connecte sur l'appareil, il lui est rattaché.
        PushDevice::query()->updateOrCreate(['token' => $data['token']], [
            'user_id' => $request->user()->id,
            'platform' => $data['platform'],
            'app' => Domains::keyForHost($request->getHost()) ?? 'app',
            'last_seen_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);

        PushDevice::query()->where('token', $data['token'])->where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true]);
    }
}
