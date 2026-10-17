<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\AccessScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    private const ROADMAP_GROUP = 'project';
    private const ROADMAP_KEY = 'admin_roadmap';

    public function index(Request $request): View|Response
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            abort(403, 'Acces admin refuse.');
        }

        $roadmap = $this->loadRoadmap();
        $summary = $this->computeSummary($roadmap);

        return response()->view('admin.index', [
            'roadmap' => $roadmap,
            'summary' => $summary,
            'statusOptions' => [
                'todo' => 'A faire',
                'in_progress' => 'En cours',
                'done' => 'Termine',
                'blocked' => 'Bloque',
            ],
        ]);
    }

    public function updateAction(Request $request): RedirectResponse
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            abort(403, 'Acces admin refuse.');
        }

        $payload = $request->validate([
            'module_id' => ['required', 'string', 'max:120'],
            'action_id' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:todo,in_progress,done,blocked'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $roadmap = $this->loadRoadmap();

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
                break;
            }

            break;
        }

        unset($module, $action);

        $this->saveRoadmap($roadmap);

        return back()->with('status', 'Roadmap mise a jour.');
    }

    private function loadRoadmap(): array
    {
        $setting = Setting::query()->firstOrNew([
            'group' => self::ROADMAP_GROUP,
            'key' => self::ROADMAP_KEY,
        ]);

        if (! $setting->exists || empty($setting->value)) {
            $roadmap = $this->defaultRoadmap();
            $setting->value = json_encode($roadmap, JSON_UNESCAPED_UNICODE);
            $setting->save();

            return $roadmap;
        }

        $decoded = json_decode((string) $setting->value, true);
        if (! is_array($decoded) || ! isset($decoded['modules']) || ! is_array($decoded['modules'])) {
            $roadmap = $this->defaultRoadmap();
            $setting->value = json_encode($roadmap, JSON_UNESCAPED_UNICODE);
            $setting->save();

            return $roadmap;
        }

        return $decoded;
    }

    private function saveRoadmap(array $roadmap): void
    {
        Setting::query()->updateOrCreate(
            [
                'group' => self::ROADMAP_GROUP,
                'key' => self::ROADMAP_KEY,
            ],
            [
                'value' => json_encode($roadmap, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private function computeSummary(array $roadmap): array
    {
        $total = 0;
        $done = 0;
        $inProgress = 0;
        $blocked = 0;

        foreach ($roadmap['modules'] as &$module) {
            $moduleTotal = count($module['actions']);
            $moduleDone = 0;

            foreach ($module['actions'] as $action) {
                $total++;
                if ($action['status'] === 'done') {
                    $done++;
                    $moduleDone++;
                }
                if ($action['status'] === 'in_progress') {
                    $inProgress++;
                }
                if ($action['status'] === 'blocked') {
                    $blocked++;
                }
            }

            $module['progress'] = $moduleTotal > 0 ? (int) round(($moduleDone / $moduleTotal) * 100) : 0;
        }

        unset($module);

        return [
            'total_actions' => $total,
            'done_actions' => $done,
            'in_progress_actions' => $inProgress,
            'blocked_actions' => $blocked,
            'global_progress' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    private function defaultRoadmap(): array
    {
        return [
            'project_name' => 'T.Link V2 - Plan global',
            'updated_at' => now()->toDateTimeString(),
            'modules' => [
                [
                    'id' => 'foundation',
                    'title' => 'Module 1 - Foundation Technique',
                    'goal' => 'Stabiliser la base Laravel, auth, roles, permissions et CI/CD.',
                    'actions' => [
                        ['id' => 'foundation-auth', 'label' => 'Valider auth web/API et gestion session', 'status' => 'done', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'foundation-roles', 'label' => 'Finaliser matrice roles/permissions', 'status' => 'in_progress', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'foundation-release', 'label' => 'Verifier pipeline release VPS sans interruption', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                    ],
                ],
                [
                    'id' => 'social',
                    'title' => 'Module 2 - Social & Feed',
                    'goal' => 'Consolider publication, commentaires, reactions et moderation.',
                    'actions' => [
                        ['id' => 'social-post', 'label' => 'Ameliorer experience creation post', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'social-moderation', 'label' => 'Actions moderation depuis UI admin', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'social-metrics', 'label' => 'Ajouter KPIs engagement', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                    ],
                ],
                [
                    'id' => 'governance',
                    'title' => 'Module 3 - Gouvernance & Workflow',
                    'goal' => 'Industrialiser adhesions, approbations et escalades.',
                    'actions' => [
                        ['id' => 'gov-membership', 'label' => 'Ecran de traitement adhesions en lot', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'gov-approval', 'label' => 'Historique decisions + filtres avances', 'status' => 'in_progress', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'gov-audit', 'label' => 'Rapprocher logs audit et workflows', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                    ],
                ],
                [
                    'id' => 'events',
                    'title' => 'Module 4 - Evenements & Mobilisation',
                    'goal' => 'Optimiser creation events et inscriptions participants.',
                    'actions' => [
                        ['id' => 'events-calendar', 'label' => 'Vue calendrier operationnelle', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'events-capacity', 'label' => 'Gestion capacite + waitlist UI', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'events-reminders', 'label' => 'Notifications rappels evenement', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                    ],
                ],
                [
                    'id' => 'ops',
                    'title' => 'Module 5 - Ops, Qualite & Securite',
                    'goal' => 'Renforcer observabilite, tests, hardening et docs d exploitation.',
                    'actions' => [
                        ['id' => 'ops-tests', 'label' => 'Couvrir workflows critiques par tests feature', 'status' => 'in_progress', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'ops-monitoring', 'label' => 'Mettre en place checks health et alerting', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                        ['id' => 'ops-playbook', 'label' => 'Rediger playbook incident + rollback', 'status' => 'todo', 'note' => '', 'updated_at' => now()->toDateTimeString()],
                    ],
                ],
            ],
        ];
    }
}
