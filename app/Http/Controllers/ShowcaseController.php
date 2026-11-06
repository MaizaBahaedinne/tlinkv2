<?php

namespace App\Http\Controllers;

use App\Models\AcademyCourse;
use App\Models\AcademyPlan;
use App\Models\StoreProduct;
use App\Support\Academy\AcademySettings;
use App\Support\Domains;
use App\Support\SiteSettings;
use App\Support\Store\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Pages d'accueil publiques des sous-domaines Store et Academy (aucune connexion requise). Elles reprennent la mise en page
 * (barre de navigation, pied de page, langue) du site de la Fondation : présentation du service, aperçu du catalogue et
 * invitation à se connecter.
 */
class ShowcaseController extends Controller
{
    public function store(Request $request): View
    {
        $open = StoreSettings::enabled();

        return $this->render($request, 'site.showcase.store', 'store', [
            'settings' => StoreSettings::all(),
            'open' => $open,
            'products' => $open
                ? StoreProduct::query()->where('is_active', true)
                    ->with(['category:id,name', 'variants' => fn ($query) => $query->where('is_active', true)])
                    ->orderByDesc('is_featured')->orderByDesc('id')->limit(8)->get()
                : collect(),
        ]);
    }

    public function academy(Request $request): View
    {
        return $this->render($request, 'site.showcase.academy', 'academy', [
            'name' => AcademySettings::NAME,
            'courses' => AcademyCourse::query()->where('status', 'published')
                ->withCount(['modules', 'lessons'])
                ->orderBy('sort_order')->orderBy('id')->limit(6)->get(),
            'plans' => AcademyPlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    /**
     * Ces pages n'ont pas de segment de langue : on prend celle du navigateur, comme la racine du site. La connexion et
     * l'inscription vivent sur l'hôte T.Link ; la session est partagée entre les sous-domaines.
     *
     * @param  array<string, mixed>  $data
     */
    private function render(Request $request, string $view, string $service, array $data): View
    {
        $locale = $request->getPreferredLanguage(array_keys(config('site.locales'))) ?? config('site.default_locale');

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return view($view, $data + [
            'site' => new SiteSettings(),
            'service' => $service,
            'loginUrl' => Domains::url('app', route('login', [], false)),
            'registerUrl' => Domains::url('app', route('register', [], false)),
        ]);
    }
}
