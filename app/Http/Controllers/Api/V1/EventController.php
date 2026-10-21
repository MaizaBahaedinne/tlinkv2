<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Support\AccessScope;
use App\Support\AuditLogger;
use App\Support\OrgScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = FoundationEvent::query()
            ->latest('starts_at')
            ->paginate(min((int) $request->integer('per_page', 12), 50));

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scope' => ['nullable', Rule::in(OrgScopes::slugs())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'approval_required' => ['nullable', 'boolean'],
        ]);

        if (! AccessScope::can($request->user(), 'event.manage', $data['scope'] ?? null)) {
            return response()->json(['message' => 'Permission insuffisante.'], 403);
        }

        $event = FoundationEvent::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'organizer_id' => $request->user()->id,
            'scope' => $data['scope'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'location' => $data['location'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'approval_required' => (bool) ($data['approval_required'] ?? false),
            'status' => 'published',
        ]);

        AuditLogger::record(
            actorId: $request->user()->id,
            action: 'event.created',
            targetType: FoundationEvent::class,
            targetId: $event->id,
            metadata: ['scope' => $event->scope, 'capacity' => $event->capacity],
            ip: $request->ip(),
        );

        AppNotification::query()->create([
            'user_id' => $request->user()->id,
            'type' => 'event.published',
            'title' => 'Evenement publie',
            'body' => 'Votre evenement "'.$event->title.'" est publie.',
            'data' => ['event_id' => $event->id],
        ]);

        return response()->json([
            'message' => 'Evenement cree.',
            'data' => $event,
        ], 201);
    }

    public function register(Request $request, FoundationEvent $event): JsonResponse
    {
        $existing = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Inscription deja existante.'], 409);
        }

        $registration = DB::transaction(function () use ($request, $event) {
            $confirmedCount = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('status', 'confirmed')
                ->lockForUpdate()
                ->count();

            $status = 'confirmed';
            if ($event->capacity !== null && $confirmedCount >= $event->capacity) {
                $status = 'waitlisted';
            }

            return EventRegistration::query()->create([
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
                'status' => $status,
                'registered_at' => now(),
            ]);
        });

        AuditLogger::record(
            actorId: $request->user()->id,
            action: 'event.registration.created',
            targetType: EventRegistration::class,
            targetId: $registration->id,
            metadata: ['event_id' => $event->id, 'status' => $registration->status],
            ip: $request->ip(),
        );

        AppNotification::query()->create([
            'user_id' => $request->user()->id,
            'type' => 'event.registration',
            'title' => 'Inscription evenement',
            'body' => $registration->status === 'confirmed'
                ? 'Votre inscription est confirmee.'
                : 'Vous etes en liste d\'attente.',
            'data' => ['event_id' => $event->id, 'registration_id' => $registration->id, 'status' => $registration->status],
        ]);

        return response()->json([
            'message' => 'Inscription enregistree.',
            'data' => $registration,
        ], 201);
    }
}
