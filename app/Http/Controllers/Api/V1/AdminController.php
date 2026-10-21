<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\User;
use App\Support\AccessScope;
use App\Support\ScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    private function guard(Request $request): ?JsonResponse
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            return response()->json(['message' => 'Acces admin refuse.'], 403);
        }

        return null;
    }

    public function overview(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $scopes = ScopeResolver::managedScopes($request->user(), 'entity.manage');
        $isGlobal = $scopes === [];

        $membershipsPending = Membership::query()
            ->when(! $isGlobal, fn ($q) => $q->whereIn('scope', $scopes))
            ->where('status', 'pending')
            ->count();

        $approvalsPending = ApprovalRequest::query()
            ->where('status', 'pending')
            ->count();

        $postsCount = Post::query()
            ->when(! $isGlobal, fn ($q) => $q->whereIn('scope', $scopes))
            ->count();

        $usersCount = User::query()->count();

        $recentAudit = AuditLog::query()->latest('id')->limit(10)->get();
        $recentReports = ModerationReport::query()->latest('id')->limit(10)->get();

        return response()->json([
            'data' => [
                'is_global' => $isGlobal,
                'managed_scopes' => $scopes,
                'stats' => [
                    'users' => $usersCount,
                    'posts' => $postsCount,
                    'memberships_pending' => $membershipsPending,
                    'approvals_pending' => $approvalsPending,
                ],
                'recent_audit_logs' => $recentAudit,
                'recent_moderation_reports' => $recentReports,
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $users = User::query()
            ->when($request->filled('q'), function ($q) use ($request): void {
                $needle = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($needle): void {
                    $inner->where('name', 'like', $needle)->orWhere('email', 'like', $needle);
                });
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')->toString()))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('to')->toString()))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($users);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $logs = AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')->toString()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('from')->toString()))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('to')->toString()))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($logs);
    }
}
