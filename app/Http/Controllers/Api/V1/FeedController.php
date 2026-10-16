<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->with(['author:id,name'])
            ->latest('published_at')
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 12), 50));

        return response()->json($posts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'visibility' => ['required', 'in:public,authenticated,entity,department,club,restricted'],
            'type' => ['required', 'in:text,image,link,announcement,project_update,activity_report,event_announcement'],
        ]);

        $post = Post::query()->create([
            'user_id' => $request->user()->id,
            'content' => $data['content'],
            'visibility' => $data['visibility'],
            'type' => $data['type'],
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'Publication creee.',
            'data' => $post,
        ], 201);
    }
}
