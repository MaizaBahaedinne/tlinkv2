<?php

namespace App\Http\Controllers\Admin;

use App\Models\StoreDeliveryMethod;
use App\Models\StoreOrder;
use App\Models\StorePaymentMethod;
use App\Models\StoreProduct;
use App\Models\StoreReturn;
use App\Models\StoreVariant;
use App\Support\Store\StoreSettings;
use Illuminate\View\View;

/** Vue d'ensemble de Tunivisions Stores : chiffres clés, mise en route, dernières commandes et stock à surveiller. */
class StoreDashboardController extends AdminController
{
    public function index(): View
    {
        $activeVariants = StoreVariant::query()->where('is_active', true)->whereHas('product', fn ($query) => $query->where('is_active', true));

        $steps = [
            ['done' => StoreProduct::query()->where('is_active', true)->whereHas('variants', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0))->exists(), 'title' => 'Ajouter un article en vente avec du stock', 'text' => 'Créez l’article, puis ses variantes (couleurs et tailles) et leur stock.', 'route' => 'admin.store.products.create', 'label' => 'Créer un article'],
            ['done' => StoreDeliveryMethod::query()->where('is_active', true)->exists(), 'title' => 'Activer un mode de livraison', 'text' => 'Livraison à domicile et/ou retrait sur place, avec frais et délais.', 'route' => 'admin.store.settings.edit', 'label' => 'Configurer la livraison'],
            ['done' => StorePaymentMethod::query()->where('is_active', true)->exists(), 'title' => 'Activer un mode de paiement', 'text' => 'À la livraison, espèces au retrait, virement…', 'route' => 'admin.store.settings.edit', 'label' => 'Configurer le paiement'],
            ['done' => StoreSettings::enabled(), 'title' => 'Ouvrir la boutique aux membres', 'text' => 'Tant qu’elle est fermée, l’onglet Boutique reste invisible.', 'route' => 'admin.store.settings.edit', 'label' => 'Ouvrir la boutique'],
        ];

        return view('admin.store.index', [
            'enabled' => StoreSettings::enabled(),
            'steps' => $steps,
            'ready' => collect($steps)->every(fn (array $step): bool => $step['done']),
            'pending' => StoreOrder::query()->where('status', 'pending')->count(),
            'toShip' => StoreOrder::query()->whereIn('status', ['confirmed', 'preparing'])->count(),
            'revenue' => (float) StoreOrder::query()->where('status', '!=', 'cancelled')->where('created_at', '>=', now()->subDays(30))->sum('total'),
            'returnsOpen' => StoreReturn::query()->where('status', 'requested')->count(),
            'lowCount' => (clone $activeVariants)->whereColumn('stock', '<=', 'low_stock_threshold')->count(),
            'recentOrders' => StoreOrder::query()->latest('id')->limit(8)->get(),
            'lowVariants' => (clone $activeVariants)->whereColumn('stock', '<=', 'low_stock_threshold')->with(['product:id,name', 'color:id,name,hex', 'size:id,name'])->orderBy('stock')->limit(8)->get(),
        ]);
    }
}
