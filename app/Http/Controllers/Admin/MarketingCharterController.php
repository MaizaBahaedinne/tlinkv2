<?php

namespace App\Http\Controllers\Admin;

use App\Models\MarketingCharter;
use App\Support\DigitalManagers;
use App\Support\MarketingCharters;
use App\Support\MarketingEntities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Chartes d'usage des réseaux sociaux : une charte commune à toutes les entités, et si besoin une charte propre
 * à un club, un chapitre ou un projet. Le texte signé par le membre est figé au moment de la signature.
 */
class MarketingCharterController extends AdminController
{
    private const MODULE = 'marketing.review';

    public function index(Request $request): View
    {
        $this->authorizeReviewer($request);

        return view('admin.marketing.charters.index', [
            'default' => MarketingCharter::query()->where('entity', 'default')->first(),
            'custom' => MarketingCharter::query()->where('entity', '!=', 'default')->orderBy('entity')->get(),
            'entityOptions' => MarketingEntities::grouped(MarketingEntities::all()),
            'canUpdate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['update']),
        ]);
    }

    public function edit(Request $request): View
    {
        $this->authorizeReviewer($request);

        $entity = $this->entity((string) $request->query('entity', 'default'));
        $charter = MarketingCharter::query()->where('entity', $entity)->first();
        $fallback = MarketingCharter::query()->where('entity', 'default')->first();

        return view('admin.marketing.charters.edit', [
            'entity' => $entity,
            'entityLabel' => $entity === 'default' ? 'Toutes les entités' : MarketingEntities::find($entity)['label'],
            'charter' => $charter,
            'title' => $charter?->title ?? $fallback?->title ?? MarketingCharters::DEFAULT_TITLE,
            'body' => $charter?->body ?? $fallback?->body ?? MarketingCharters::DEFAULT_BODY,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeReviewer($request, 'update');

        $data = $request->validate([
            'entity' => ['required', 'string'],
            'title' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $entity = $this->entity($data['entity']);
        $charter = MarketingCharter::query()->where('entity', $entity)->first();

        if ($charter === null) {
            $charter = MarketingCharter::query()->create(['entity' => $entity, 'title' => $data['title'], 'body' => $data['body'], 'version' => 1, 'updated_by' => $request->user()->id]);
        } else {
            // Toute modification du texte crée une nouvelle version : les signatures déjà données gardent leur texte d'origine.
            $changed = $charter->title !== $data['title'] || $charter->body !== $data['body'];
            $charter->update(['title' => $data['title'], 'body' => $data['body'], 'version' => $charter->version + ($changed ? 1 : 0), 'updated_by' => $request->user()->id]);
        }

        $this->audit($request, 'admin.marketing.charter.updated', $charter, ['entity' => $entity, 'version' => $charter->version]);

        return redirect()->route('admin.marketing.charters.index')->with('status', 'Charte enregistrée (version '.$charter->version.').');
    }

    /** Supprime une charte propre à une entité : elle revient à la charte commune. */
    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizeReviewer($request, 'update');

        $entity = $this->entity((string) $request->input('entity'));
        abort_if($entity === 'default', 422, 'La charte commune ne se supprime pas.');

        $charter = MarketingCharter::query()->where('entity', $entity)->first();

        if ($charter !== null) {
            $this->audit($request, 'admin.marketing.charter.deleted', $charter, ['entity' => $entity]);
            $charter->delete();
        }

        return redirect()->route('admin.marketing.charters.index')->with('status', 'Charte propre supprimée : la charte commune s’applique.');
    }

    private function entity(string $key): string
    {
        abort_unless($key === 'default' || MarketingEntities::find($key) !== null, 404);

        return $key;
    }

    private function authorizeReviewer(Request $request, string $action = 'read'): void
    {
        $this->requireModuleAction($request, self::MODULE, $action);
        abort_unless(DigitalManagers::canReview($request->user(), $action), 403, 'La validation des demandes est réservée aux postes habilités par l’administration.');
    }
}
