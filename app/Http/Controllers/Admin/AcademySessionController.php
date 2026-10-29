<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademyCourse;
use App\Models\AcademySession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sessions en direct du calendrier Academy.
 */
class AcademySessionController extends AdminController
{
    public function index(Request $request): View
    {
        $past = $request->boolean('passees');

        $sessions = AcademySession::query()
            ->with('course:id,title')
            ->when($past, fn ($query) => $query->where('starts_at', '<', now())->orderByDesc('starts_at'))
            ->when(! $past, fn ($query) => $query->where('starts_at', '>=', now())->orderBy('starts_at'))
            ->paginate(30)->withQueryString();

        return view('admin.academy.sessions.index', [
            'sessions' => $sessions,
            'past' => $past,
            'courses' => AcademyCourse::query()->orderBy('title')->pluck('title', 'id')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $session = AcademySession::query()->create($this->validated($request));
        $this->audit($request, 'admin.academy.session.saved', $session, ['title' => $session->title]);

        return back()->with('status', 'Session ajoutée au calendrier.');
    }

    public function update(Request $request, AcademySession $session): RedirectResponse
    {
        $session->update($this->validated($request));
        $this->audit($request, 'admin.academy.session.saved', $session, ['title' => $session->title]);

        return back()->with('status', 'Session enregistrée.');
    }

    public function destroy(Request $request, AcademySession $session): RedirectResponse
    {
        $this->audit($request, 'admin.academy.session.deleted', $session, ['title' => $session->title]);
        $session->delete();

        return back()->with('status', 'Session supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'course_id' => ['nullable', 'integer', Rule::exists('academy_courses', 'id')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }
}
