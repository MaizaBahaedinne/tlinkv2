<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\User;
use App\Support\AccessScope;
use App\Support\ScopeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View|Response
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            abort(403, 'Acces admin refuse.');
        }

        $entityIds = ScopeResolver::managedEntityIds($request->user(), 'entity.manage');
        $isGlobal = $entityIds === [];
        $windowDays = max(7, min(180, (int) $request->query('window_days', 30)));
        $moderationStatus = (string) $request->query('moderation_status', 'pending');
        $approvalStatus = (string) $request->query('approval_status', 'pending');
        $auditAction = trim((string) $request->query('audit_action', ''));
        $fromDate = now()->subDays($windowDays)->startOfDay();

        $approvalItemsQuery = ApprovalRequest::query()->with('requester')->latest('id');
        if ($approvalStatus !== 'all') {
            $approvalItemsQuery->where('status', $approvalStatus);
        }

        $recentModerationQuery = ModerationReport::query()->latest('id');
        if ($moderationStatus !== 'all') {
            $recentModerationQuery->where('status', $moderationStatus);
        }

        $recentAuditLogsQuery = AuditLog::query()->latest('id');
        if ($auditAction !== '') {
            $recentAuditLogsQuery->where('action', 'like', '%'.$auditAction.'%');
        }

        $stats = [
            'posts' => Post::query()->when(! $isGlobal, fn ($q) => $q->whereIn('entity_id', $entityIds))->count(),
            'memberships_pending' => Membership::query()->when(! $isGlobal, fn ($q) => $q->whereIn('entity_id', $entityIds))->where('status', 'pending')->count(),
            'approvals_pending' => ApprovalRequest::query()->where('status', 'pending')->count(),
            'moderation_pending' => ModerationReport::query()->where('status', 'pending')->count(),
            'new_users_window' => User::query()->where('created_at', '>=', $fromDate)->count(),
        ];

        return response()->view('admin.index', [
            'isGlobal' => $isGlobal,
            'entityIds' => $entityIds,
            'stats' => $stats,
            'windowDays' => $windowDays,
            'moderationStatus' => $moderationStatus,
            'approvalStatus' => $approvalStatus,
            'auditAction' => $auditAction,
            'recentUsers' => User::query()
                ->where('created_at', '>=', $fromDate)
                ->latest('id')
                ->limit(10)
                ->get(['id', 'name', 'email', 'created_at']),
            'approvalItems' => $approvalItemsQuery->limit(10)->get(),
            'recentAuditLogs' => $recentAuditLogsQuery->limit(14)->get(),
            'recentModeration' => $recentModerationQuery->limit(10)->get(),
        ]);
    }
}
