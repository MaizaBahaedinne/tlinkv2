@php
    $words = preg_split('/\s+/u', trim((string) $holder['name'])) ?: [];
    $initials = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
@endphp
<span class="flex items-center gap-3">
    @if($holder['photo_url'])
        <img src="{{ $holder['photo_url'] }}" alt="" loading="lazy" class="h-9 w-9 shrink-0 rounded-full object-cover">
    @else
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600" aria-hidden="true">{{ $initials }}</span>
    @endif
    <span>
        <span class="font-medium text-slate-800">{{ $holder['name'] }}</span> <x-verified-badge :verified="$holder['verified'] ?? false" />
        @if($holder['job_title'])<span class="text-slate-500"> · {{ $holder['job_title'] }}</span>@endif
        @if(! empty($withMailto) && $holder['email'])<span class="text-slate-500"> · <a class="underline" href="mailto:{{ $holder['email'] }}">{{ $holder['email'] }}</a></span>
        @elseif($holder['email'])<span class="text-slate-500"> · {{ $holder['email'] }}</span>@endif
    </span>
</span>
