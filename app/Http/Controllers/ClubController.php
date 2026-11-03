<?php

namespace App\Http\Controllers;

use App\Models\ClubNews;
use App\Models\OrgNode;
use App\Support\AuditLogger;
use App\Support\ClubPage;
use App\Support\OrgScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClubController extends Controller
{
    /** Annuaire des clubs actifs. */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $scope = OrgScopes::valid((string) $request->query('scope')) ? (string) $request->query('scope') : null;

        $clubs = OrgNode::query()
            ->where('kind', 'club')
            ->where('status', 'active')
            ->with(['institution', 'governorate'])
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($scope !== null, fn ($query) => $query->where('scope', $scope))
            ->orderBy('title')
            ->paginate(24)
            ->withQueryString();

        return view('clubs.index', [
            'clubs' => $clubs,
            'search' => $search,
            'scope' => $scope,
            'scopes' => OrgScopes::all(),
        ]);
    }

    /** Page complète : bandeau + onglet demandé (à propos par défaut) ; les autres onglets sont chargés à la demande. */
    public function show(Request $request, OrgNode $club): View
    {
        $page = $this->page($request, $club);
        $tab = ClubPage::tab($request->query('onglet'));
        $club->load(['institution', 'governorate']);

        return view('clubs.show', [
            'club' => $club,
            'hero' => $page->hero(),
            'tab' => $tab,
            'panel' => $page->panel($tab),
        ]);
    }

    /** Fragment HTML d'un onglet, appelé en JavaScript. */
    public function tab(Request $request, OrgNode $club, string $tab): Response
    {
        $page = $this->page($request, $club);
        $club->load(['institution', 'governorate']);

        return response(view('clubs.tabs.'.$tab, $page->panel($tab))->render())
            ->header('Cache-Control', 'private, no-store');
    }

    public function storeNews(Request $request, OrgNode $club): RedirectResponse
    {
        $page = $this->page($request, $club);
        abort_unless($page->canManage(), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'cover' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $news = ClubNews::create([
            'club_id' => $club->id,
            'user_id' => $request->user()->id,
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'cover_path' => $request->hasFile('cover') ? $request->file('cover')->store('clubs/news', 'public') : null,
            'published_at' => now(),
        ]);

        AuditLogger::record($request->user()->id, 'club.news.published', ClubNews::class, $news->id, ['club' => $club->title, 'title' => Str::limit($news->title, 80)]);

        return redirect()->route('club.show', [$club, 'onglet' => 'actualites'])->with('status', 'Actualité publiée.');
    }

    public function destroyNews(Request $request, OrgNode $club, ClubNews $news): RedirectResponse
    {
        $page = $this->page($request, $club);
        abort_unless($page->canManage() && $news->club_id === $club->id, 403);

        if ($news->cover_path) {
            Storage::disk('public')->delete($news->cover_path);
        }

        AuditLogger::record($request->user()->id, 'club.news.deleted', ClubNews::class, $news->id, ['club' => $club->title, 'title' => Str::limit($news->title, 80)]);
        $news->delete();

        return redirect()->route('club.show', [$club, 'onglet' => 'actualites'])->with('status', 'Actualité supprimée.');
    }

    private function page(Request $request, OrgNode $club): ClubPage
    {
        abort_unless($club->kind === 'club', 404);

        $page = new ClubPage($club, $request->user());
        abort_if($club->status === 'draft' && ! $page->canManage(), 404);

        return $page;
    }
}
