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

        return view('app', [
            'posts' => $posts,
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
