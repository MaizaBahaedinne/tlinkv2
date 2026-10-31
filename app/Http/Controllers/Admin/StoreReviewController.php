<?php

namespace App\Http\Controllers\Admin;

use App\Models\StoreReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Modération des avis de la boutique : masquer, republier ou supprimer. */
class StoreReviewController extends AdminController
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        $reviews = StoreReview::query()
            ->with(['product:id,name,slug', 'user:id,name'])
            ->when(in_array($status, [StoreReview::PUBLISHED, StoreReview::HIDDEN], true), fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)->withQueryString();

        return view('admin.store.reviews.index', ['reviews' => $reviews, 'status' => $status]);
    }

    public function update(Request $request, StoreReview $review): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.StoreReview::PUBLISHED.','.StoreReview::HIDDEN]]);

        $review->update($data);
        $this->audit($request, 'admin.store.review.moderated', $review, ['status' => $data['status'], 'product_id' => $review->product_id]);

        return back()->with('status', $data['status'] === StoreReview::HIDDEN ? 'Avis masqué.' : 'Avis republié.');
    }

    public function destroy(Request $request, StoreReview $review): RedirectResponse
    {
        $productId = $review->product_id;
        $review->delete();
        $this->audit($request, 'admin.store.review.deleted', $review, ['product_id' => $productId]);

        return back()->with('status', 'Avis supprimé.');
    }
}
