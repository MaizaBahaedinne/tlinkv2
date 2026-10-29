@php
    $tones = [
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'neutral' => 'bg-slate-100 text-slate-600 ring-slate-200',
        'danger' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
@endphp
<span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $tones[$tone ?? 'neutral'] ?? $tones['neutral'] }}">{{ $label }}</span>
