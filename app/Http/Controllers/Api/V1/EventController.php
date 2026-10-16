<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Models\FoundationEvent;
use App\Support\AccessScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'entity_id' => ['nullable', 'integer', 'exists:entities,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'approval_required' => ['nullable', 'boolean'],
        ]);

        if (! AccessScope::can($request->user(), 'event.manage', $data['entity_id'] ?? null)) {
            return response()->json(['message' => 'Permission insuffisante.'], 403);
        }

        $event = FoundationEvent::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'organizer_id' => $request->user()->id,
            'entity_id' => $data['entity_id'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'location' => $data['location'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'approval_required' => (bool) ($data['approval_required'] ?? false),
            'status' => 'published',
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

        return response()->json([
            'message' => 'Inscription enregistree.',
            'data' => $registration,
        ], 201);
    }
}
