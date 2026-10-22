@php
    $id = $id ?? 'password';
    $label = $label ?? 'Mot de passe';
    $autocomplete = $autocomplete ?? 'current-password';
@endphp
<div>
    <label class="mb-1.5 block text-sm font-semibold text-slate-700" for="{{ $id }}">{{ $label }}</label>
    <div class="relative">
        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <input id="{{ $id }}" name="{{ $id }}" type="password" required autocomplete="{{ $autocomplete }}"
               class="w-full rounded-xl border border-slate-300 bg-white py-3 pl-11 pr-12 text-sm outline-none transition focus:border-tlink-blue focus:ring-4 focus:ring-tlink-blue/10">
        @if ($toggle ?? true)
            <button type="button" data-toggle-password="{{ $id }}" aria-pressed="false" aria-label="Afficher ou masquer le mot de passe"
                    class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 transition hover:text-slate-700">
                <svg data-eye class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg data-eye-off class="hidden h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17.94 17.94A10.9 10.9 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 5.24A10.9 10.9 0 0 1 12 5c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>
            </button>
        @endif
    </div>
    @error($id) <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
