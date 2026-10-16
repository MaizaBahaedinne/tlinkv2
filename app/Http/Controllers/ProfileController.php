<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $profile = $request->user()->profile;

        return view('profile.show', [
            'profile' => $profile,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'headline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:4000'],
            'skills' => ['nullable', 'string', 'max:500'],
            'languages' => ['nullable', 'string', 'max:500'],
        ]);

        $profile = $request->user()->profile;
        $profile->fill($data);
        $profile->save();

        return back()->with('status', 'Profil mis a jour.');
    }
}
