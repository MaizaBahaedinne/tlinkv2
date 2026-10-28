<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Membership;
use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\PostReaction;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\ProfileOrganizations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Profil d'un autre membre, en lecture seule. Aucune coordonnée (e-mail, téléphone, CIN, adresse) n'est exposée.
 */
class MemberProfileController extends Controller
{
    /** Publications du membre visibles de tout membre connecté. */
    private const PUBLIC_VISIBILITIES = ['public', 'authenticated'];

    public function show(Request $request, User $user): View|RedirectResponse
    {
        if ($request->user()->is($user)) {
            return redirect()->route('profile.show');
        }

        abort_unless($user->is_active, 404);

        $profile = $user->profile()->firstOrNew([]);
        $sections = (new ProfileOrganizations())->sections($profile);

        $recentPosts = $user->posts()
            ->whereIn('visibility', self::PUBLIC_VISIBILITIES)
            ->withCount(['comments', 'reactions'])
            ->latest('published_at')
            ->latest('id')
            ->take(5)
            ->get(['id', 'content', 'visibility', 'published_at']);

        return view('profile.member', [
            'member' => $user,
            'profile' => $profile,
            'avatarUrl' => $profile->photo_path && Storage::disk('public')->exists($profile->photo_path)
                ? Storage::disk('public')->url($profile->photo_path)
                : null,
            'coverUrl' => $profile->cover_path && Storage::disk('public')->exists($profile->cover_path)
                ? Storage::disk('public')->url($profile->cover_path)
                : null,
            'avatarInitials' => Str::upper(Str::substr(
                collect(explode(' ', $user->name))->filter()->map(fn (string $part) => Str::substr($part, 0, 1))->join(''),
                0,
                2
            )),
            ...$sections,
            'earnedBadges' => UserBadge::earnedBy($user->id),
            'clubs' => $this->clubs($user),
            'positions' => $this->positions($user),
            'recentPosts' => $recentPosts,
            'stats' => [
                'posts_count' => $user->posts()->whereIn('visibility', self::PUBLIC_VISIBILITIES)->count(),
                'comments_count' => Comment::query()->where('user_id', $user->id)->count(),
                'reactions_count' => PostReaction::query()->where('user_id', $user->id)->count(),
            ],
        ]);
    }

    /**
     * Clubs où le membre est adhérent (adhésion validée et en cours), avec leur logo.
     *
     * @return \Illuminate\Support\Collection<int, OrgNode>
     */
    private function clubs(User $user)
    {
        $today = now()->toDateString();

        return Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereNotNull('node_id')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with('node')
            ->get()
            ->map(fn (Membership $membership): ?OrgNode => $this->clubOf($membership->node))
            ->filter()
            ->unique('id')
            ->values();
    }

    /** Club auquel appartient un bloc : lui-même s'il en est un, sinon son plus proche parent qui en est un. */
    private function clubOf(?OrgNode $node): ?OrgNode
    {
        for ($guard = 0; $guard < 20 && $node !== null; $guard++) {
            if ($node->kind === 'club') {
                return $node;
            }

            $node = $node->parent_id !== null ? OrgNode::query()->find($node->parent_id) : null;
        }

        return null;
    }

    /**
     * Postes occupés en ce moment au sein de la Fondation.
     *
     * @return \Illuminate\Support\Collection<int, array{title: string, club: ?OrgNode}>
     */
    private function positions(User $user)
    {
        $today = now()->toDateString();

        return OrgAssignment::query()
            ->whereHas('person', fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('node', fn ($query) => $query->where('kind', 'position'))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $today))
            ->with('node')
            ->get()
            ->map(fn (OrgAssignment $assignment): array => [
                'title' => $assignment->node->title,
                'club' => $this->clubOf($assignment->node),
            ])
            ->values();
    }
}
