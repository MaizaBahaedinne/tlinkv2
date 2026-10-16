<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\ModerationReport;
use App\Models\Post;
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

        $stats = [
            'posts' => Post::query()->when(! $isGlobal, fn ($q) => $q->whereIn('entity_id', $entityIds))->count(),
            'memberships_pending' => Membership::query()->when(! $isGlobal, fn ($q) => $q->whereIn('entity_id', $entityIds))->where('status', 'pending')->count(),
            'approvals_pending' => ApprovalRequest::query()->where('status', 'pending')->count(),
            'moderation_pending' => ModerationReport::query()->where('status', 'pending')->count(),
        ];

        return response()->view('admin.index', [
            'isGlobal' => $isGlobal,
            'entityIds' => $entityIds,
            'stats' => $stats,
            'recentAuditLogs' => AuditLog::query()->latest('id')->limit(12)->get(),
            'recentModeration' => ModerationReport::query()->latest('id')->limit(12)->get(),
        ]);
    }
}
