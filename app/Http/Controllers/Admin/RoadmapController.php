<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoadmapController extends AdminController
{
    private const GROUP = 'project';
    private const KEY = 'admin_roadmap';

    private const STATUSES = [
        'todo' => 'À faire',
        'in_progress' => 'En cours',
        'done' => 'Terminé',
        'blocked' => 'Bloqué',
    ];

    public function index(): View
    {
        $roadmap = $this->load();

        $total = 0;
        $done = 0;
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);

        foreach ($roadmap['modules'] as &$module) {
            $moduleDone = 0;
            foreach ($module['actions'] as $action) {
                $total++;
                $counts[$action['status']] = ($counts[$action['status']] ?? 0) + 1;
                if ($action['status'] === 'done') {
                    $done++;
                    $moduleDone++;
                }
            }
            $module['progress'] = count($module['actions']) > 0
                ? (int) round(($moduleDone / count($module['actions'])) * 100)
                : 0;
        }
        unset($module);

        return view('admin.roadmap.index', [
            'roadmap' => $roadmap,
            'statuses' => self::STATUSES,
            'counts' => $counts,
            'total' => $total,
            'progress' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'module_id' => ['required', 'string', 'max:120'],
            'action_id' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:'.implode(',', array_keys(self::STATUSES))],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $roadmap = $this->load();
        $found = false;

        foreach ($roadmap['modules'] as &$module) {
            if ($module['id'] !== $payload['module_id']) {
                continue;
            }

            foreach ($module['actions'] as &$action) {
                if ($action['id'] !== $payload['action_id']) {
                    continue;
                }

                $action['status'] = $payload['status'];
                $action['note'] = trim((string) ($payload['note'] ?? ''));
                $action['updated_at'] = now()->toDateTimeString();
                $found = true;
                break;
            }
            unset($action);

            break;
        }
        unset($module);

        abort_unless($found, 404, 'Action introuvable.');

        $roadmap['updated_at'] = now()->toDateTimeString();
        $this->save($roadmap);

        $setting = Setting::query()->where('group', self::GROUP)->where('key', self::KEY)->first();
        $this->audit($request, 'admin.roadmap.updated', $setting, [
            'module' => $payload['module_id'],
            'action' => $payload['action_id'],
            'status' => $payload['status'],
        ]);

        return back()->with('status', 'Feuille de route mise à jour.');
    }

    private function load(): array
    {
        $setting = Setting::query()->firstOrNew(['group' => self::GROUP, 'key' => self::KEY]);
        $decoded = $setting->exists && ! empty($setting->value) ? json_decode((string) $setting->value, true) : null;

        if (! is_array($decoded) || ! isset($decoded['modules']) || ! is_array($decoded['modules'])) {
            $decoded = $this->defaults();
            $this->save($decoded);
        }

        return $decoded;
    }

    private function save(array $roadmap): void
    {
        Setting::query()->updateOrCreate(
            ['group' => self::GROUP, 'key' => self::KEY],
            ['value' => json_encode($roadmap, JSON_UNESCAPED_UNICODE)]
        );
    }

    private function defaults(): array
    {
        $now = now()->toDateTimeString();
        $action = fn (string $id, string $label, string $status = 'todo', string $note = ''): array => [
            'id' => $id, 'label' => $label, 'status' => $status, 'note' => $note, 'updated_at' => $now,
        ];

        return [
            'project_name' => 'T.Link V2 — Plan global',
            'updated_at' => $now,
            'modules' => [
                [
                    'id' => 'foundation',
                    'title' => 'Module 1 — Fondation technique',
                    'goal' => 'Stabiliser la base Laravel, auth, rôles, permissions et CI/CD.',
                    'actions' => [
                        $action('foundation-auth', 'Valider auth web/API et gestion session', 'done'),
                        $action('foundation-roles', 'Finaliser matrice rôles/permissions', 'done', 'Matrice centralisée et sync complète par rôle.'),
                        $action('foundation-release', 'Vérifier pipeline release VPS sans interruption'),
                    ],
                ],
                [
                    'id' => 'social',
                    'title' => 'Module 2 — Social & fil d\'actualité',
                    'goal' => 'Consolider publication, commentaires, réactions et modération.',
                    'actions' => [
                        $action('social-post', 'Améliorer l\'expérience de création de post'),
                        $action('social-moderation', 'Actions de modération depuis l\'UI admin', 'done', 'Disponible dans Contenu > Modération.'),
                        $action('social-metrics', 'Ajouter KPIs d\'engagement'),
                    ],
                ],
                [
                    'id' => 'governance',
                    'title' => 'Module 3 — Gouvernance & workflow',
                    'goal' => 'Industrialiser adhésions, approbations et escalades.',
                    'actions' => [
                        $action('gov-membership', 'Écran de traitement des adhésions', 'done'),
                        $action('gov-approval', 'Historique des décisions + filtres avancés', 'in_progress'),
                        $action('gov-audit', 'Rapprocher logs d\'audit et workflows', 'in_progress'),
                    ],
                ],
                [
                    'id' => 'events',
                    'title' => 'Module 4 — Événements & mobilisation',
                    'goal' => 'Optimiser création d\'événements et inscriptions participants.',
                    'actions' => [
                        $action('events-calendar', 'Vue calendrier opérationnelle'),
                        $action('events-capacity', 'Gestion capacité + liste d\'attente UI', 'done'),
                        $action('events-reminders', 'Notifications de rappel d\'événement'),
                    ],
                ],
                [
                    'id' => 'ops',
                    'title' => 'Module 5 — Ops, qualité & sécurité',
                    'goal' => 'Renforcer observabilité, tests, hardening et docs d\'exploitation.',
                    'actions' => [
                        $action('ops-tests', 'Couvrir les workflows critiques par des tests feature', 'in_progress'),
                        $action('ops-monitoring', 'Mettre en place checks health et alerting'),
                        $action('ops-playbook', 'Rédiger playbook incident + rollback'),
                    ],
                ],
            ],
        ];
    }
}
