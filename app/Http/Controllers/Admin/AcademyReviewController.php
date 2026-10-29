<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademyReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Modération des avis Academy : masquer, réafficher ou supprimer. */
class AcademyReviewController extends AdminController
{
    public function toggle(Request $request, AcademyReview $review): RedirectResponse
    {
        $review->update(['is_hidden' => ! $review->is_hidden]);
        $this->audit($request, 'admin.academy.review.moderated', $review, ['course_id' => $review->course_id, 'hidden' => $review->is_hidden]);

        return back()->with('status', $review->is_hidden ? 'Avis masqué.' : 'Avis de nouveau visible.');
    }

    public function destroy(Request $request, AcademyReview $review): RedirectResponse
    {
        $this->audit($request, 'admin.academy.review.deleted', $review, ['course_id' => $review->course_id]);
        $review->delete();

        return back()->with('status', 'Avis supprimé.');
    }
}
