<?php

namespace App\Http\Controllers\Admin;

use App\Models\NewsletterSubscriber;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Abonnés à la newsletter du site public.
 */
class NewsletterController extends AdminController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'unsubscribed', 'all'])],
        ]);

        $status = $filters['status'] ?? 'active';
        $term = trim((string) ($filters['q'] ?? ''));

        return view('admin.newsletter.index', [
            'subscribers' => NewsletterSubscriber::query()
                ->when($status === 'active', fn ($query) => $query->active())
                ->when($status === 'unsubscribed', fn ($query) => $query->whereNotNull('unsubscribed_at'))
                ->when($term !== '', fn ($query) => $query->where('email', 'like', '%'.addcslashes($term, '\\%_').'%'))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
            'term' => $term,
            'counts' => [
                'active' => NewsletterSubscriber::query()->active()->count(),
                'unsubscribed' => NewsletterSubscriber::query()->whereNotNull('unsubscribed_at')->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        AuditLogger::record($request->user()->id, 'admin.newsletter.exported', null, null, [], $request->ip());

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'langue', 'inscrit_le', 'lien_desinscription']);

            NewsletterSubscriber::query()->active()->orderBy('id')->chunkById(500, function ($chunk) use ($out): void {
                foreach ($chunk as $subscriber) {
                    fputcsv($out, [
                        $this->safeCell($subscriber->email),
                        $subscriber->locale,
                        $subscriber->subscribed_at?->format('Y-m-d H:i'),
                        route('site.newsletter.unsubscribe', ['locale' => $subscriber->locale, 'token' => $subscriber->unsubscribe_token]),
                    ]);
                }
            });

            fclose($out);
        }, 'abonnes-newsletter-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        $this->audit($request, 'admin.newsletter.deleted', $subscriber, ['email' => $subscriber->email]);
        $subscriber->delete();

        return back()->with('status', 'Abonné supprimé.');
    }

    /** Empêche l'interprétation d'une cellule comme formule dans un tableur. */
    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
