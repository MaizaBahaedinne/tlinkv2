<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Models\StoryView;
use App\Support\Stories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stories 24 h : publication, vue, suppression et lecture du média (réservé aux membres connectés). */
class StoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(Stories::enabled(), 404);

        $request->validate([
            'media' => [
                'required', 'file', 'max:20480',
                Rule::when(
                    in_array((string) $request->file('media')?->getMimeType(), ['video/mp4', 'video/webm', 'video/quicktime'], true),
                    ['mimetypes:video/mp4,video/webm,video/quicktime'],
                    ['mimes:jpg,jpeg,png,webp', 'max:5120'],
                ),
            ],
            'caption' => ['nullable', 'string', 'max:160'],
        ], [
            'media.required' => 'Choisissez une photo ou une vidéo.',
            'media.mimes' => 'La story doit être une photo JPG, PNG ou WebP, ou une vidéo MP4, WebM ou MOV.',
            'media.mimetypes' => 'La story doit être une photo JPG, PNG ou WebP, ou une vidéo MP4, WebM ou MOV.',
            'media.max' => 'Ce fichier est trop volumineux (photo : 5 Mo, vidéo : 20 Mo).',
        ]);

        $user = $request->user();

        if (Story::query()->active()->where('user_id', $user->id)->count() >= Stories::MAX_ACTIVE_PER_USER) {
            return response()->json(['message' => 'Vous avez atteint la limite de '.Stories::MAX_ACTIVE_PER_USER.' stories actives. Attendez qu’une expire ou supprimez-en une.'], 422);
        }

        $story = Stories::create($user, $request->file('media'), $request->input('caption'));

        return response()->json(['id' => $story->id], 201);
    }

    /** Marque la story comme vue (une seule fois par membre ; l'auteur ne compte pas). */
    public function view(Request $request, Story $story): JsonResponse
    {
        abort_unless(Stories::enabled() && $story->isActive(), 404);

        if ($story->user_id !== $request->user()->id) {
            StoryView::query()->firstOrCreate(['story_id' => $story->id, 'user_id' => $request->user()->id]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Story $story): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);

        Storage::disk(Story::DISK)->delete($story->path);
        $story->delete();

        return response()->json(['ok' => true]);
    }

    public function media(Story $story): StreamedResponse
    {
        abort_unless(Stories::enabled() && $story->isActive() && Storage::disk(Story::DISK)->exists($story->path), 404);

        return Storage::disk(Story::DISK)->response($story->path, basename($story->path), [
            'Content-Type' => $story->mime_type,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
