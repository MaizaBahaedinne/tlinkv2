<?php

namespace App\Http\Controllers;

use App\Models\AcademyCourse;
use App\Models\AcademyPlan;
use App\Models\StoreProduct;
use App\Support\Academy\AcademySettings;
use App\Support\Domains;
use App\Support\Store\StoreSettings;
use Illuminate\View\View;

/**
 * Pages d'accueil publiques des sous-domaines Store et Academy (aucune connexion requise) : présentation du service,
 * aperçu du catalogue et invitation à se connecter.
 */
class ShowcaseController extends Controller
{
    public function store(): View
    {
        $open = StoreSettings::enabled();

        return view('showcase.store', $this->common('store') + [
            'title' => StoreSettings::get('name').' | Fondation Tunivisions',
            'settings' => StoreSettings::all(),
            'open' => $open,
            'products' => $open
                ? StoreProduct::query()->where('is_active', true)
                    ->with(['category:id,name', 'variants' => fn ($query) => $query->where('is_active', true)])
                    ->orderByDesc('is_featured')->orderByDesc('id')->limit(8)->get()
                : collect(),
        ]);
    }

    public function academy(): View
    {
        return view('showcase.academy', $this->common('academy') + [
            'title' => AcademySettings::NAME.' | Fondation Tunivisions',
            'name' => AcademySettings::NAME,
            'courses' => AcademyCourse::query()->where('status', 'published')
                ->withCount(['modules', 'lessons'])
                ->orderBy('sort_order')->orderBy('id')->limit(6)->get(),
            'plans' => AcademyPlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    /**
     * Liens communs aux vitrines. La connexion et l'inscription vivent sur l'hôte T.Link ; la session est partagée
     * entre les sous-domaines, on revient donc sur la vitrine une fois connecté.
     *
     * @return array<string, string>
     */
    private function common(string $service): array
    {
        return [
            'service' => $service,
            'siteUrl' => Domains::url('site'),
            'appUrl' => Domains::url('app'),
            'loginUrl' => Domains::url('app', route('login', [], false)),
            'registerUrl' => Domains::url('app', route('register', [], false)),
        ];
    }
}
