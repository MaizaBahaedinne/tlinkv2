<?php

namespace App\Http\Controllers\Admin;

use App\Models\MailLog;
use App\Support\MailTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Journal des e-mails envoyés par la plateforme, regroupé par adresse : réservé à l'administration globale.
 */
class MailLogController extends AdminController
{
    public function index(Request $request): View
    {
        abort_unless($this->context($request)->isGlobal('entity.manage'), 403, 'Réservé à l’administration globale.');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'template' => (string) $request->query('template', ''),
            'email' => mb_strtolower(trim((string) $request->query('email', ''))),
        ];

        $base = MailLog::query()
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('to_email', 'like', '%'.addcslashes($filters['q'], '%_\\').'%')
                ->orWhere('to_name', 'like', '%'.addcslashes($filters['q'], '%_\\').'%')))
            ->when($filters['template'] !== '', fn ($query) => $query->where('template', $filters['template']));

        // Un envoi sans confirmation depuis plus de 5 minutes est compté en échec.
        $stale = now()->subMinutes(5)->toDateTimeString();

        $this->applyStatus($base, $filters['status'], $stale);

        $totals = [
            'all' => MailLog::query()->count(),
            'sent' => MailLog::query()->where('status', 'sent')->count(),
            'failed' => MailLog::query()->where(fn ($query) => $query->where('status', 'failed')->orWhere(fn ($inner) => $inner->where('status', 'pending')->where('created_at', '<', $stale)))->count(),
        ];

        if ($filters['email'] !== '') {
            $lines = (clone $base)->where('to_email', $filters['email'])->latest('id')->paginate(30)->withQueryString();
            $addresses = null;
        } else {
            $addresses = (clone $base)
                ->select('to_email')
                ->selectRaw('max(to_name) as name')
                ->selectRaw('count(*) as total')
                ->selectRaw("sum(case when status = 'sent' then 1 else 0 end) as sent_count")
                ->selectRaw("sum(case when status = 'failed' or (status = 'pending' and created_at < ?) then 1 else 0 end) as failed_count", [$stale])
                ->selectRaw('max(created_at) as last_at')
                ->groupBy('to_email')
                ->orderByDesc(DB::raw('max(created_at)'))
                ->paginate(25)
                ->withQueryString();
            $lines = null;
        }

        return view('admin.mail.logs', [
            'filters' => $filters,
            'addresses' => $addresses,
            'lines' => $lines,
            'totals' => $totals,
            'templates' => collect(MailTemplates::CATALOG)->map(fn (array $item): string => $item['label'])->all(),
        ]);
    }

    private function applyStatus($query, string $status, string $stale): void
    {
        match ($status) {
            'sent' => $query->where('status', 'sent'),
            'failed' => $query->where(fn ($inner) => $inner->where('status', 'failed')->orWhere(fn ($pending) => $pending->where('status', 'pending')->where('created_at', '<', $stale))),
            'pending' => $query->where('status', 'pending')->where('created_at', '>=', $stale),
            default => null,
        };
    }
}
