{{-- Pastille d'un expéditeur : initiales sur une couleur stable (toujours la même pour une même adresse). --}}
@props(['name' => '', 'email' => '', 'size' => 'h-10 w-10 text-sm'])
@php
    $palette = [
        'bg-rose-100 text-rose-700', 'bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700', 'bg-sky-100 text-sky-700',
        'bg-violet-100 text-violet-700', 'bg-teal-100 text-teal-700', 'bg-orange-100 text-orange-700', 'bg-indigo-100 text-indigo-700',
        'bg-lime-100 text-lime-700', 'bg-fuchsia-100 text-fuchsia-700',
    ];
    $label = trim((string) $name) !== '' ? trim((string) $name) : (string) strstr((string) $email, '@', true);
    $words = array_values(array_filter(preg_split('/[\s._-]+/u', $label) ?: []));
    $initials = mb_strtoupper(count($words) > 1 ? mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1) : mb_substr($words[0] ?? '?', 0, 2));
    $color = $palette[abs(crc32(strtolower((string) ($email ?: $name)))) % count($palette)];
@endphp
<span {{ $attributes->class(['grid shrink-0 select-none place-items-center rounded-full font-semibold', $size, $color]) }} aria-hidden="true">{{ $initials }}</span>
