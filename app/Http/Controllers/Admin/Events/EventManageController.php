<?php

namespace App\Http\Controllers\Admin\Events;

use App\Http\Controllers\Admin\AdminController;
use App\Models\FoundationEvent;
use App\Support\Events\EventAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Pages de gestion d'un événement (billetterie, programme, sponsors, paiements, équipe OC).
 * Ces routes ne passent pas par les modules d'administration : les droits viennent de EventAccess
 * (responsable de l'organisation ou rôle dans l'équipe OC de l'événement).
 */
abstract class EventManageController extends AdminController
{
    protected function allow(Request $request, FoundationEvent $event, string $ability): void
    {
        abort_unless(
            EventAccess::can($request->user(), $event, $ability),
            403,
            'Votre rôle dans l’équipe de cet événement ne couvre pas cette action.'
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(Request $request, FoundationEvent $event, string $tab, string $view, array $data = []): View
    {
        return view($view, $data + [
            'event' => $event,
            'tab' => $tab,
            'abilities' => EventAccess::abilities($request->user(), $event),
            'isOrganizer' => EventAccess::isOrganizer($request->user(), $event),
        ]);
    }

    protected function manageAudit(Request $request, FoundationEvent $event, string $what): void
    {
        $this->audit($request, 'event.manage.updated', $event, ['what' => $what]);
    }

    protected function storePublicImage(Request $request, string $field, string $directory, ?string $previous = null): ?string
    {
        if (! $request->hasFile($field)) {
            return $previous;
        }

        $path = $request->file($field)->store($directory, 'public');

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }
}
