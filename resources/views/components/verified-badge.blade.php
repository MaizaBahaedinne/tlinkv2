@props(['user' => null, 'verified' => null, 'size' => 'text-[0.9em]'])
{{-- Badge « Profil vérifié » (identité validée par un administrateur), à placer juste après un nom. $user = membre, ou $verified = booléen déjà connu. --}}
@php($isVerified = $verified ?? ($user?->profile?->identity_verified_at !== null))
@if($isVerified)
    <i class="fa-solid fa-circle-check shrink-0 align-middle text-sky-500 {{ $size }}" title="Profil vérifié" role="img" aria-label="Profil vérifié"></i>
@endif
