<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\PostReaction;
use App\Support\ProfileOrganizations;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $organizations = new ProfileOrganizations();
        $sections = $organizations->sections($profile);

        $recentPosts = $user->posts()
            ->withCount(['comments', 'reactions'])
            ->latest('published_at')
            ->latest('id')
            ->take(5)
            ->get(['id', 'content', 'visibility', 'published_at']);

        return view('profile.show', [
            'user' => $user,
            'profile' => $profile,
            'avatarUrl' => $profile->photo_path && Storage::disk('public')->exists($profile->photo_path)
                ? Storage::disk('public')->url($profile->photo_path)
                : null,
            'coverUrl' => $profile->cover_path && Storage::disk('public')->exists($profile->cover_path)
                ? Storage::disk('public')->url($profile->cover_path)
                : null,
            'skills' => $skills,
            'languages' => $languages,
            'educationItems' => $educationItems,
            'experienceItems' => $experienceItems,
            ...$sections,
            'companyOptions' => $organizations->companyOptions(),
            'schoolOptions' => $organizations->schoolOptions(),
            'clubOptions' => $organizations->clubOptions(),
            'recentPosts' => $recentPosts,
            'earnedBadges' => \App\Models\UserBadge::earnedBy($user->id),
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
        $section = (string) $request->input('section', 'basic');

        if ($section === 'media') {
            return $this->updateMedia($request);
        }

        if ($section !== 'basic') {
            return $this->updateSection($request, $section);
        }

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

    private function updateMedia(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => ['nullable', 'file', 'image', 'max:5120'],
            'cover' => ['nullable', 'file', 'image', 'max:10240'],
        ]);

        $profile = $request->user()->profile()->firstOrCreate([]);

        if ($request->hasFile('avatar')) {
            if ($profile->photo_path && Storage::disk('public')->exists($profile->photo_path)) {
                Storage::disk('public')->delete($profile->photo_path);
            }

            $profile->photo_path = $request->file('avatar')->store('profiles/avatars', 'public');
        }

        if ($request->hasFile('cover')) {
            if ($profile->cover_path && Storage::disk('public')->exists($profile->cover_path)) {
                Storage::disk('public')->delete($profile->cover_path);
            }

            $profile->cover_path = $request->file('cover')->store('profiles/covers', 'public');
        }

        $profile->save();

        return back()->with('status', 'Photo de profil mise a jour.');
    }

    private function updateSection(Request $request, string $section): RedirectResponse
    {
        $profile = $request->user()->profile()->firstOrCreate([]);
        $settings = is_array($profile->privacy_settings) ? $profile->privacy_settings : [];

        if ($section === 'professional_experiences') {
            $data = $request->validate([
                'items' => ['nullable', 'array', 'max:25'],
                'items.*.company' => ['nullable', 'string', 'max:160'],
                'items.*.role' => ['nullable', 'string', 'max:160'],
                'items.*.start_date' => ['nullable', 'date'],
                'items.*.end_date' => ['nullable', 'date'],
                'items.*.description' => ['nullable', 'string', 'max:2000'],
            ]);

            $settings['professional_experiences'] = $this->cleanRows($data['items'] ?? [], ['company', 'role', 'start_date', 'end_date', 'description']);
        }

        if ($section === 'educations') {
            $data = $request->validate([
                'items' => ['nullable', 'array', 'max:25'],
                'items.*.degree_type' => ['nullable', 'string', 'max:80'],
                'items.*.diploma_name' => ['nullable', 'string', 'max:180'],
                'items.*.school' => ['nullable', 'string', 'max:180'],
                'items.*.start_date' => ['nullable', 'date'],
                'items.*.end_date' => ['nullable', 'date'],
            ]);

            $settings['educations'] = $this->cleanRows($data['items'] ?? [], ['degree_type', 'diploma_name', 'school', 'start_date', 'end_date']);
        }

        if ($section === 'certifications') {
            $data = $request->validate([
                'items' => ['nullable', 'array', 'max:25'],
                'items.*.name' => ['nullable', 'string', 'max:180'],
                'items.*.issuer' => ['nullable', 'string', 'max:160'],
                'items.*.date_obtained' => ['nullable', 'date'],
                'items.*.url' => ['nullable', 'url:http,https', 'max:300'],
            ]);

            $settings['certifications'] = $this->cleanRows($data['items'] ?? [], ['name', 'issuer', 'date_obtained', 'url']);
        }

        if ($section === 'associative_experiences') {
            $data = $request->validate([
                'items' => ['nullable', 'array', 'max:25'],
                'items.*.club_name' => ['nullable', 'string', 'max:160'],
                'items.*.role' => ['nullable', 'string', 'max:160'],
                'items.*.department' => ['nullable', 'string', 'max:160'],
                'items.*.start_date' => ['nullable', 'date'],
                'items.*.end_date' => ['nullable', 'date'],
                'items.*.description' => ['nullable', 'string', 'max:2000'],
            ]);

            $settings['associative_experiences'] = $this->cleanRows($data['items'] ?? [], ['club_name', 'role', 'department', 'start_date', 'end_date', 'description']);
        }

        if ($section === 'skill_keywords') {
            $data = $request->validate([
                'keywords' => ['nullable', 'string', 'max:2000'],
            ]);

            $settings['skill_keywords'] = collect(explode(',', (string) ($data['keywords'] ?? '')))
                ->map(fn (string $item) => trim($item))
                ->filter()
                ->values()
                ->all();
        }

        if ($section === 'languages') {
            $data = $request->validate([
                'items' => ['nullable', 'array', 'max:25'],
                'items.*.name' => ['nullable', 'string', 'max:100'],
                'items.*.level' => ['nullable', 'string', 'max:20'],
            ]);

            $settings['languages'] = $this->cleanRows($data['items'] ?? [], ['name', 'level']);
        }

        $profile->privacy_settings = (new ProfileOrganizations())->link($settings, $request->user()->id);
        $profile->save();

        return back()->with('status', 'Section mise a jour.');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $keys
     * @return array<int, array<string, mixed>>
     */
    private function cleanRows(array $rows, array $keys): array
    {
        return collect($rows)
            ->map(function (array $row) use ($keys): array {
                $clean = [];
                foreach ($keys as $key) {
                    $value = isset($row[$key]) ? trim((string) $row[$key]) : '';
                    $clean[$key] = $value;
                }

                return $clean;
            })
            ->filter(function (array $row): bool {
                foreach ($row as $value) {
                    if ($value !== '') {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }
}
