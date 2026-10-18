<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Support\AuditLabels;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends AdminController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $like = '%'.$needle.'%';
                $query->whereHas('actor', fn ($actor) => $actor->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action')
            ->mapWithKeys(fn (string $action) => [$action => AuditLabels::label($action)])
            ->all();

        return view('admin.audit.index', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => $filters,
        ]);
    }
}
