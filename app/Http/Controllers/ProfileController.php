<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\PostReaction;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->loadMissing('profile');
        $profile = $user->profile()->firstOrCreate([]);

        $skills = collect(explode(',', (string) $profile->skills))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values();

        $languages = collect(explode(',', (string) $profile->languages))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values();

        $educationItems = collect(preg_split('/\r\n|\r|\n/', (string) $profile->education))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values();

        $experienceItems = collect(preg_split('/\r\n|\r|\n/', (string) $profile->experience))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values();

        $recentPosts = $user->posts()
            ->withCount(['comments', 'reactions'])
            ->latest('published_at')
            ->latest('id')
            ->take(5)
            ->get(['id', 'content', 'visibility', 'published_at']);

        return view('profile.show', [
            'user' => $user,
            'profile' => $profile,
            'skills' => $skills,
            'languages' => $languages,
            'educationItems' => $educationItems,
            'experienceItems' => $experienceItems,
            'recentPosts' => $recentPosts,
            'stats' => [
                'posts_count' => $user->posts()->count(),
                'comments_count' => Comment::query()->where('user_id', $user->id)->count(),
                'reactions_count' => PostReaction::query()->where('user_id', $user->id)->count(),
            ],
            'avatarInitials' => Str::upper(Str::substr(
                collect(explode(' ', $user->name))
                    ->filter()
                    ->map(fn (string $part) => Str::substr($part, 0, 1))
                    ->join(''),
                0,
                2
            )),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'headline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:4000'],
            'experience' => ['nullable', 'string', 'max:4000'],
            'education' => ['nullable', 'string', 'max:4000'],
            'skills' => ['nullable', 'string', 'max:500'],
            'languages' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = $request->user()->profile()->firstOrCreate([]);
        $profile->fill($data);
        $profile->save();

        return back()->with('status', 'Profil mis a jour.');
    }
}
