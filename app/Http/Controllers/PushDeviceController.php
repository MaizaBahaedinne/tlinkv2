<?php

namespace App\Http\Controllers;

use App\Models\PushDevice;
use App\Support\Domains;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    /** Compte rendu de l'application sur l'état des notifications (plugin absent, permission refusée, erreur Firebase…), pour le diagnostic. */
    public function diagnostic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'step' => ['required', 'string', 'max:60'],
            'detail' => ['nullable', 'string', 'max:300'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        Log::info('Push appareil : '.$data['step'], ['user' => $request->user()->id, 'detail' => $data['detail'] ?? null, 'platform' => $data['platform'] ?? null, 'host' => $request->getHost()]);

        return response()->json(['ok' => true]);
    }
}
