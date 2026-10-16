<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Governorate;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::query()
            ->with(['author:id,name'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(12);

        $feedPayload = $posts->getCollection()->map(function (Post $post): array {
            return [
                'id' => $post->id,
                'content' => $post->content,
                'type' => $post->type,
                'visibility' => $post->visibility,
                'published_at' => optional($post->published_at)?->toIso8601String(),
                'author' => $post->author
                    ? ['id' => $post->author->id, 'name' => $post->author->name]
                    : null,
            ];
        })->values();

        return view('app', [
            'posts' => $posts,
            'feedPayload' => $feedPayload,
            'user' => $request->user(),
            'entities' => Entity::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'governorates' => Governorate::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function storePost(Request $request): RedirectResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'visibility' => ['required', 'in:public,authenticated,entity,department,club,restricted'],
        ]);

        Post::query()->create([
            'user_id' => $request->user()->id,
            'type' => 'text',
            'visibility' => $request->string('visibility')->toString(),
            'content' => $request->string('content')->toString(),
            'published_at' => now(),
        ]);

        return back()->with('status', 'Publication partagee.');
    }
}
