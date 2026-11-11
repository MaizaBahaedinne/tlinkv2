<?php

namespace App\Http\Controllers;

use App\Support\Domains;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Identité installable de chaque application : un manifeste par sous-domaine (nom, icônes, page de départ) et les
 * déclarations « Digital Asset Links » qui permettent à Android d'ouvrir l'application installée à la place du navigateur.
 */
class AppManifestController extends Controller
{
    /** @return array{id: string, section: string, path: string, name: string, icons: string}|null */
    private function appFor(Request $request): ?array
    {
        $section = Domains::keyForHost($request->getHost());
        $section = in_array($section, [null, 'site'], true) ? 'app' : $section;

        foreach ((array) config('mobile.apps') as $id => $app) {
            if ($app['section'] === $section) {
                return ['id' => $id] + $app;
            }
        }

        return null;
    }

    public function manifest(Request $request): JsonResponse
    {
        $app = $this->appFor($request) ?? ['id' => 'com.tunivisions.tlink', 'section' => 'app', 'path' => '/feed', 'name' => 'T.Link', 'icons' => 'link'];
        $icons = fn (int $size): array => ['src' => asset("app-icons/{$app['icons']}/icon-{$size}.png"), 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any'];

        return response()->json([
            'id' => $app['path'],
            'name' => $app['name'],
            'short_name' => $app['short_name'] ?? $app['name'],
            'description' => $app['description'] ?? 'Fondation Tunivisions',
            'lang' => 'fr',
            'start_url' => $app['path'],
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#d00031',
            'icons' => [$icons(192), $icons(512)],
            // Permet au navigateur Android de savoir si l'application (native ou installée depuis le navigateur) est déjà installée (navigator.getInstalledRelatedApps).
            'related_applications' => [
                ['platform' => 'play', 'id' => $app['id']],
                ['platform' => 'webapp', 'url' => route('manifest')],
            ],
            'prefer_related_applications' => false,
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Lie l'hôte à l'application Android correspondante (empreintes de signature dans config/mobile.php). */
    public function assetLinks(Request $request): JsonResponse
    {
        $app = $this->appFor($request);
        $fingerprints = array_values(array_filter(
            (array) config('mobile.android_fingerprints'),
            fn ($value): bool => is_string($value) && preg_match('/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $value) === 1,
        ));

        $statements = $app === null || $fingerprints === [] ? [] : [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => $app['id'], 'sha256_cert_fingerprints' => $fingerprints],
        ]];

        return response()->json($statements, 200, ['Cache-Control' => 'public, max-age=300']);
    }
}
