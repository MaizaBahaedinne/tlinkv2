<?php

namespace App\Http\Controllers\Admin;

use App\Models\Advert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Bannières publicitaires (image ou GIF) du fil T.Link.
 */
class AdvertController extends AdminController
{
    public function index(Request $request): View
    {
        return view('admin.adverts.index', [
            'adverts' => Advert::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'link_url' => ['nullable', 'url:http,https', 'max:255'],
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'is_active' => ['nullable', 'boolean'],
        ], self::imageMessages() + ['image.required' => 'L’image ou le GIF est obligatoire.']);

        $advert = Advert::query()->create([
            'title' => trim($data['title']),
            'link_url' => $data['link_url'] ?? null,
            'image_path' => $request->file('image')->store('adverts', 'public'),
            'sort_order' => ((int) Advert::query()->max('sort_order')) + 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit($request, 'admin.advert.created', $advert, ['title' => $advert->title]);

        return back()->with('status', 'Publicité ajoutée.');
    }

    public function update(Request $request, Advert $advert): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'link_url' => ['nullable', 'url:http,https', 'max:255'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], self::imageMessages());

        $values = [
            'title' => trim($data['title']),
            'link_url' => $data['link_url'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? $advert->sort_order),
            'is_active' => $request->boolean('is_active'),
        ];

        $oldImage = null;

        if ($request->hasFile('image')) {
            $oldImage = $advert->image_path;
            $values['image_path'] = $request->file('image')->store('adverts', 'public');
        }

        $advert->update($values);
        $this->deleteImage($oldImage);

        $this->audit($request, 'admin.advert.updated', $advert, ['title' => $advert->title, 'active' => $advert->is_active]);

        return back()->with('status', 'Publicité modifiée.');
    }

    public function destroy(Request $request, Advert $advert): RedirectResponse
    {
        $this->audit($request, 'admin.advert.deleted', $advert, ['title' => $advert->title]);

        $image = $advert->image_path;
        $advert->delete();
        $this->deleteImage($image);

        return back()->with('status', 'Publicité supprimée.');
    }

    /** @return array<string, string> */
    private static function imageMessages(): array
    {
        return [
            'image.uploaded' => 'Le fichier n’a pas pu être envoyé : il dépasse la limite actuelle du serveur PHP ('.ini_get('upload_max_filesize').' par fichier, '.ini_get('post_max_size').' par envoi). Relevez upload_max_filesize et post_max_size dans la configuration PHP du serveur, ou réduisez le poids du GIF.',
            'image.max' => 'Le fichier est trop lourd (5 Mo max). Réduisez le poids du GIF.',
            'image.image' => 'Le fichier doit être une image ou un GIF.',
            'image.mimes' => 'Formats acceptés : jpg, png, webp ou gif.',
        ];
    }

    private function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'adverts/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
