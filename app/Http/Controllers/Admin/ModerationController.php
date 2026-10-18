<?php

namespace App\Http\Controllers\Admin;

use App\Models\ModerationReport;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModerationController extends AdminController
{
    public const STATUSES = [
        'pending' => 'À traiter',
        'reviewed' => 'Traités',
        'rejected' => 'Rejetés',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_merge(array_keys(self::STATUSES), ['all']))],
        ]);

        $status = $filters['status'] ?? 'pending';

        $base = $this->visibleReports($request);

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        $reports = (clone $base)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $posts = Post::query()
            ->with('author:id,name')
            ->whereIn('id', $reports->getCollection()->pluck('reportable_id'))
            ->get()
            ->keyBy('id');

        $people = \App\Models\User::query()
            ->whereIn('id', $reports->getCollection()->pluck('reported_by')->merge($reports->getCollection()->pluck('reviewed_by'))->filter()->unique())
            ->pluck('name', 'id');

        return view('admin.moderation.index', [
            'reports' => $reports,
            'posts' => $posts,
            'people' => $people,
            'status' => $status,
            'statuses' => self::STATUSES,
            'counts' => $counts,
        ]);
    }

    public function update(Request $request, ModerationReport $report): RedirectResponse
    {
        $post = $report->reportable_type === Post::class ? Post::query()->find($report->reportable_id) : null;

        // Un signalement dont la publication a disparu reste traitable par un administrateur global.
        $this->authorizeEntity($request, 'post.moderate', $post?->entity_id);

        $data = $request->validate([
            'status' => ['required', Rule::in(['reviewed', 'rejected'])],
        ]);

        $report->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->audit($request, 'admin.moderation.reviewed', $report, [
            'status' => $data['status'],
            'post_id' => $report->reportable_id,
        ]);

        return back()->with('status', $data['status'] === 'reviewed' ? 'Signalement validé.' : 'Signalement rejeté.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ModerationReport>
     */
    private function visibleReports(Request $request)
    {
        $scope = $this->context($request)->scope('post.moderate');
        abort_if($scope === null, 403, 'Acces refuse.');

        $query = ModerationReport::query()->where('reportable_type', Post::class);

        if ($scope === []) {
            return ModerationReport::query();
        }

        return $query->whereIn('reportable_id', Post::query()->whereIn('entity_id', $scope)->select('id'));
    }
}
