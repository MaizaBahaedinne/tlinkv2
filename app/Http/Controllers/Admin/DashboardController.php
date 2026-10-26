<?php

namespace App\Http\Controllers\Admin;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\FoundationEvent;
use App\Models\Membership;
use App\Models\OrgNode;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends AdminController
{
    private const CHART_DAYS = 14;

    public function __invoke(Request $request): View
    {
        $ctx = $this->context($request);
        $user = $request->user();

        $canPlatform = $ctx->allowsModule(['entity.manage'], 'organization.chart');
        $canUsers = $ctx->allowsModule(['entity.manage'], 'members.users');
        $canClubs = $ctx->allowsModule(['entity.manage'], 'organization.clubs');
        $canAudit = $ctx->allowsModule(['entity.manage'], 'governance.audit');
        $canMemberships = $ctx->allowsModule(['membership.manage'], 'members.memberships');
        $canApprovals = $ctx->allowsModule(['approval.review'], 'governance.approvals');
        $canModeration = $ctx->allowsModule(['post.moderate'], 'governance.moderation');
        $canEvents = $ctx->allowsModule(['event.manage'], 'events.events');

        $stats = [];

        if ($canUsers) {
            $stats[] = [
                'label' => 'Utilisateurs',
                'value' => User::query()->count(),
                'icon' => 'user',
                'tone' => 'blue',
                'hint' => '+'.User::query()->where('created_at', '>=', now()->subDays(30))->count().' sur 30 jours',
                'href' => route('admin.users.index'),
            ];
        }

        if ($canClubs) {
            $stats[] = [
                'label' => 'Clubs',
                'value' => $this->scoped(OrgNode::query()->where('kind', 'club'), $request, 'entity.manage')->count(),
                'icon' => 'users',
                'tone' => 'cyan',
                'hint' => 'Actifs : '.$this->scoped(OrgNode::query()->where('kind', 'club'), $request, 'entity.manage')->where('status', 'active')->count(),
                'href' => route('orgchart.index'),
            ];
        }

        if ($canMemberships) {
            $stats[] = [
                'label' => 'Adhésions en attente',
                'value' => $this->scoped(Membership::query(), $request, 'membership.manage')->where('status', 'pending')->count(),
                'icon' => 'user-check',
                'tone' => 'amber',
                'hint' => 'À traiter',
                'href' => route('admin.memberships.index', ['status' => 'pending']),
            ];
        }

        if ($canApprovals) {
            $stats[] = [
                'label' => 'Approbations à traiter',
                'value' => $this->pendingApprovalsQuery($user->id)->count(),
                'icon' => 'shield',
                'tone' => 'violet',
                'hint' => 'Qui vous sont assignées',
                'href' => route('admin.approvals.index'),
            ];
        }

        if ($canModeration) {
            $stats[] = [
                'label' => 'Signalements ouverts',
                'value' => ModerationReport::query()->where('status', 'pending')->count(),
                'icon' => 'flag',
                'tone' => 'red',
                'hint' => 'Publications : '.Post::query()->count(),
                'href' => route('admin.moderation.index'),
            ];
        }

        if ($canEvents) {
            $stats[] = [
                'label' => 'Événements à venir',
                'value' => $this->scoped(FoundationEvent::query(), $request, 'event.manage')
                    ->where('status', 'published')
                    ->where('starts_at', '>=', now())
                    ->count(),
                'icon' => 'calendar',
                'tone' => 'green',
                'hint' => 'Publiés',
                'href' => route('admin.events.index'),
            ];
        }

        return view('admin.dashboard', [
            'stats' => $stats,
            'chart' => $canPlatform ? $this->activityChart() : null,
            'pendingMemberships' => $canMemberships
                ? $this->scoped(Membership::query(), $request, 'membership.manage')
                    ->with(['user:id,name,email'])
                    ->where('status', 'pending')
                    ->latest('id')
                    ->limit(5)
                    ->get()
                : null,
            'pendingReports' => $canModeration
                ? ModerationReport::query()->where('status', 'pending')->latest('id')->limit(5)->get()
                : null,
            'organization' => $canPlatform
                ? $this->organization($request)
                : null,
            'recentAudit' => $canAudit
                ? AuditLog::query()->with('actor:id,name')->latest('id')->limit(7)->get()
                : null,
            'today' => Carbon::now()->locale('fr')->translatedFormat('l j F Y'),
            'firstName' => explode(' ', trim($user->name))[0] ?? $user->name,
            'ctx' => $ctx,
        ]);
    }

    /**
     * Perimetres geres avec leurs effectifs : clubs de l'organigramme et adhesions.
     *
     * @return array<int, array{slug: string, name: string, clubs: int, memberships: int}>
     */
    private function organization(Request $request): array
    {
        $options = $this->scopeOptions($request, 'entity.manage');
        $clubs = OrgNode::query()->where('kind', 'club')->whereIn('scope', array_keys($options))->groupBy('scope')->selectRaw('scope, count(*) as total')->pluck('total', 'scope');
        $members = Membership::query()->whereIn('scope', array_keys($options))->groupBy('scope')->selectRaw('scope, count(*) as total')->pluck('total', 'scope');

        return collect($options)->map(fn (string $name, string $slug): array => [
            'slug' => $slug,
            'name' => $name,
            'clubs' => (int) ($clubs[$slug] ?? 0),
            'memberships' => (int) ($members[$slug] ?? 0),
        ])->values()->all();
    }

    private function pendingApprovalsQuery(int $userId)
    {
        return ApprovalRequest::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($userId): void {
                $query->whereNull('assigned_reviewer_id')->orWhere('assigned_reviewer_id', $userId);
            });
    }

    /**
     * @return array{days: array<int, array{label: string, users: int, posts: int, users_pct: int, posts_pct: int}>, total_users: int, total_posts: int}
     */
    private function activityChart(): array
    {
        $from = now()->subDays(self::CHART_DAYS - 1)->startOfDay();

        $users = User::query()
            ->where('created_at', '>=', $from)
            ->get(['created_at'])
            ->groupBy(fn ($row) => $row->created_at->format('Y-m-d'))
            ->map->count();

        $posts = Post::query()
            ->where('created_at', '>=', $from)
            ->get(['created_at'])
            ->groupBy(fn ($row) => $row->created_at->format('Y-m-d'))
            ->map->count();

        $max = 1;
        $days = [];

        for ($i = 0; $i < self::CHART_DAYS; $i++) {
            $date = $from->copy()->addDays($i);
            $key = $date->format('Y-m-d');
            $userCount = (int) ($users[$key] ?? 0);
            $postCount = (int) ($posts[$key] ?? 0);
            $max = max($max, $userCount, $postCount);

            $days[] = [
                'label' => $date->format('d/m'),
                'users' => $userCount,
                'posts' => $postCount,
            ];
        }

        foreach ($days as &$day) {
            $day['users_pct'] = (int) round(($day['users'] / $max) * 100);
            $day['posts_pct'] = (int) round(($day['posts'] / $max) * 100);
        }
        unset($day);

        return [
            'days' => $days,
            'total_users' => array_sum(array_column($days, 'users')),
            'total_posts' => array_sum(array_column($days, 'posts')),
        ];
    }
}
