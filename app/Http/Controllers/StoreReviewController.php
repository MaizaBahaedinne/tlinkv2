<?php

namespace App\Http\Controllers;

use App\Models\StoreProduct;
use App\Models\StoreReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Avis des acheteurs : un avis par membre et par article, réservé aux commandes livrées. */
class StoreReviewController extends Controller
{
    public function store(Request $request, StoreProduct $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        if (! StoreReview::canReview($request->user(), $product)) {
            return back()->withErrors(['review' => 'Seuls les membres ayant reçu cet article peuvent donner leur avis.']);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = StoreReview::query()->firstOrNew(['product_id' => $product->id, 'user_id' => $request->user()->id]);
        $review->fill($data);

        // Un avis masqué par l'administration le reste, même modifié par son auteur.
        $review->status ??= StoreReview::PUBLISHED;
        $review->save();

        return redirect()->to(route('store.products.show', $product).'#avis')->with('status', 'Merci, votre avis a été enregistré.');
    }
}
