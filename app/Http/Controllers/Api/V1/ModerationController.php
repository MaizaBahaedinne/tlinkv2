<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Support\AccessScope;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_id' => ['required', 'integer', 'exists:posts,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $post = Post::query()->findOrFail($data['post_id']);

        $report = ModerationReport::query()->create([
            'reported_by' => $request->user()->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        AuditLogger::record(
            actorId: $request->user()->id,
            action: 'moderation.report.created',
            targetType: ModerationReport::class,
            targetId: $report->id,
            metadata: ['post_id' => $post->id],
            ip: $request->ip(),
        );

        return response()->json([
            'message' => 'Signalement cree.',
            'data' => $report,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        if (! AccessScope::can($request->user(), 'post.moderate')) {
            return response()->json(['message' => 'Permission insuffisante.'], 403);
        }

        $reports = ModerationReport::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return response()->json($reports);
    }

    public function review(Request $request, ModerationReport $moderationReport): JsonResponse
    {
        if (! AccessScope::can($request->user(), 'post.moderate')) {
            return response()->json(['message' => 'Permission insuffisante.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:reviewed,rejected'],
        ]);

        $moderationReport->status = $data['status'];
        $moderationReport->reviewed_by = $request->user()->id;
        $moderationReport->reviewed_at = now();
        $moderationReport->save();

        AuditLogger::record(
            actorId: $request->user()->id,
            action: 'moderation.report.reviewed',
            targetType: ModerationReport::class,
            targetId: $moderationReport->id,
            metadata: ['status' => $data['status']],
            ip: $request->ip(),
        );

        return response()->json([
            'message' => 'Signalement traite.',
            'data' => $moderationReport,
        ]);
    }
}
