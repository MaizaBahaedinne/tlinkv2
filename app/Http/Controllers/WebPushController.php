<?php

namespace App\Http\Controllers;

use App\Models\WebPushSubscription;
use App\Support\Domains;
use App\Support\Push\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abonnement du navigateur aux notifications push web (service worker public/sw.js).
 */
class WebPushController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        abort_unless(WebPush::isAllowedEndpoint($data['endpoint']), 422, 'Service de notification non reconnu.');

        // Un abonnement appartient au navigateur : si un autre membre s'y connecte, il lui est rattaché.
        WebPushSubscription::query()->updateOrCreate(['endpoint_hash' => WebPushSubscription::hashEndpoint($data['endpoint'])], [
            'user_id' => $request->user()->id,
            'app' => Domains::sectionOf($request) ?? 'app',
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'last_seen_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);

        WebPushSubscription::query()
            ->where('endpoint_hash', WebPushSubscription::hashEndpoint($data['endpoint']))
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** Notification de test envoyée aux navigateurs du membre connecté (affichée même si T.Link est au premier plan). */
    public function test(Request $request): JsonResponse
    {
        $section = Domains::sectionOf($request) ?? 'app';
        $app = collect(config('mobile.apps'))->firstWhere('section', $section);
        $icon = '/app-icons/'.($app['icons'] ?? 'link').'/icon-192.png';

        $results = WebPush::sendToUsers([$request->user()->id], $app['name'] ?? 'T.Link', 'Les notifications sont activées sur ce navigateur.', Domains::homeUrl($request), 'push-test', $icon, $section);

        return response()->json(['ok' => $results !== [] && collect($results)->contains('ok', true), 'sent' => count($results)]);
    }
}
