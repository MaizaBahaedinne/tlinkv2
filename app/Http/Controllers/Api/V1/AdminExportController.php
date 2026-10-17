<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Models\Membership;
use App\Models\ModerationReport;
use App\Models\User;
use App\Support\AccessScope;
use App\Support\AuditLogger;
use App\Support\ScopeResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminExportController extends Controller
{
    private function assertAdmin(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if (! AccessScope::can($request->user(), 'entity.manage')) {
            return response()->json(['message' => 'Acces admin refuse.'], 403);
        }

        return null;
    }

    public function users(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        if ($denied = $this->assertAdmin($request)) {
            return $denied;
        }

        $query = User::query()->select(['id', 'name', 'email', 'created_at']);

        AuditLogger::record($request->user()->id, 'admin.export.users', User::class, null, [], $request->ip());

        return $this->streamCsv('users_export.csv', ['id', 'name', 'email', 'created_at'], $query->cursor()->map(fn ($u) => [
            $u->id,
            $u->name,
            $u->email,
            optional($u->created_at)->toDateTimeString(),
        ]));
    }

    public function memberships(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        if ($denied = $this->assertAdmin($request)) {
            return $denied;
        }

        $entityIds = ScopeResolver::managedEntityIds($request->user(), 'entity.manage');
        $isGlobal = $entityIds === [];

        $query = Membership::query()
            ->with(['user:id,name,email', 'entity:id,name'])
            ->when(! $isGlobal, fn ($q) => $q->whereIn('entity_id', $entityIds))
            ->select(['id', 'user_id', 'entity_id', 'status', 'membership_type', 'approved_at', 'created_at']);

        AuditLogger::record($request->user()->id, 'admin.export.memberships', Membership::class, null, ['is_global' => $isGlobal], $request->ip());

        return $this->streamCsv('memberships_export.csv', ['id', 'user', 'email', 'entity', 'status', 'membership_type', 'approved_at', 'created_at'], $query->cursor()->map(fn ($m) => [
            $m->id,
            $m->user?->name,
            $m->user?->email,
            $m->entity?->name,
            $m->status,
            $m->membership_type,
            optional($m->approved_at)->toDateTimeString(),
            optional($m->created_at)->toDateTimeString(),
        ]));
    }

    public function events(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        if ($denied = $this->assertAdmin($request)) {
            return $denied;
        }

        $entityIds = ScopeResolver::managedEntityIds($request->user(), 'entity.manage');
        $isGlobal = $entityIds === [];

        $query = FoundationEvent::query()
            ->when(! $isGlobal, fn ($q) => $q->whereIn('entity_id', $entityIds))
            ->select(['id', 'title', 'status', 'capacity', 'starts_at', 'ends_at', 'created_at']);

        AuditLogger::record($request->user()->id, 'admin.export.events', FoundationEvent::class, null, ['is_global' => $isGlobal], $request->ip());

        return $this->streamCsv('events_export.csv', ['id', 'title', 'status', 'capacity', 'starts_at', 'ends_at', 'created_at'], $query->cursor()->map(fn ($e) => [
            $e->id,
            $e->title,
            $e->status,
            $e->capacity,
            optional($e->starts_at)->toDateTimeString(),
            optional($e->ends_at)->toDateTimeString(),
            optional($e->created_at)->toDateTimeString(),
        ]));
    }

    public function moderationReports(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        if ($denied = $this->assertAdmin($request)) {
            return $denied;
        }

        $query = ModerationReport::query()->select(['id', 'reportable_type', 'reportable_id', 'status', 'created_at', 'reviewed_at']);

        AuditLogger::record($request->user()->id, 'admin.export.moderation', ModerationReport::class, null, [], $request->ip());

        return $this->streamCsv('moderation_reports_export.csv', ['id', 'reportable_type', 'reportable_id', 'status', 'created_at', 'reviewed_at'], $query->cursor()->map(fn ($r) => [
            $r->id,
            $r->reportable_type,
            $r->reportable_id,
            $r->status,
            optional($r->created_at)->toDateTimeString(),
            optional($r->reviewed_at)->toDateTimeString(),
        ]));
    }

    public function eventRegistrations(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        if ($denied = $this->assertAdmin($request)) {
            return $denied;
        }

        $entityIds = ScopeResolver::managedEntityIds($request->user(), 'entity.manage');
        $isGlobal = $entityIds === [];

        $query = EventRegistration::query()
            ->with(['event:id,title,entity_id', 'user:id,name,email'])
            ->when(! $isGlobal, function ($q) use ($entityIds): void {
                $q->whereHas('event', fn ($ev) => $ev->whereIn('entity_id', $entityIds));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->select(['id', 'event_id', 'user_id', 'status', 'registered_at', 'created_at']);

        AuditLogger::record($request->user()->id, 'admin.export.event_registrations', EventRegistration::class, null, ['is_global' => $isGlobal], $request->ip());

        return $this->streamCsv('event_registrations_export.csv', ['id', 'event_title', 'user', 'email', 'status', 'registered_at', 'created_at'], $query->cursor()->map(fn ($r) => [
            $r->id,
            $r->event?->title,
            $r->user?->name,
            $r->user?->email,
            $r->status,
            optional($r->registered_at)->toDateTimeString(),
            optional($r->created_at)->toDateTimeString(),
        ]));
    }

    private function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
