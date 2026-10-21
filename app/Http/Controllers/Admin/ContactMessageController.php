<?php

namespace App\Http\Controllers\Admin;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Messages recus via le formulaire de contact du site public (reserve a l'administration globale).
 */
class ContactMessageController extends AdminController
{
    public function index(Request $request): View
    {
        $this->authorizeGlobal($request);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_merge(array_keys(ContactMessage::STATUSES), ['all']))],
        ]);

        $status = $filters['status'] ?? 'new';

        return view('admin.contact-messages.index', [
            'messages' => ContactMessage::query()
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'status' => $status,
            'statuses' => ContactMessage::STATUSES,
            'counts' => ContactMessage::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
        ]);
    }

    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ContactMessage::STATUSES))]]);

        $message->update([
            'status' => $data['status'],
            'read_at' => $data['status'] === 'new' ? null : ($message->read_at ?? now()),
        ]);

        $this->audit($request, 'admin.contact_message.updated', $message, ['status' => $data['status']]);

        return back()->with('status', 'Message mis à jour.');
    }

    public function destroy(Request $request, ContactMessage $message): RedirectResponse
    {
        $this->authorizeGlobal($request);

        $this->audit($request, 'admin.contact_message.deleted', $message, ['subject' => $message->subject]);
        $message->delete();

        return back()->with('status', 'Message supprimé.');
    }

    private function authorizeGlobal(Request $request): void
    {
        abort_unless($this->context($request)->scope('entity.manage') === [], 403, 'Réservé à l\'administration globale.');
    }
}
