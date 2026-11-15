<?php

namespace App\Http\Controllers\Admin;

use App\Models\MarketingAccount;
use App\Support\MarketingEntities;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pages et accès marketing de chaque entité (Fondation, chapitre, projet, club). On renseigne d'abord les pages
 * Facebook et Instagram : elles s'affichent sur la page de l'entité. Identifiant et mot de passe sont facultatifs et
 * ne sont déposés que pour la reprise en main ; ils sont chiffrés, jamais affichés dans les listes, et chaque
 * consultation est inscrite au journal d'audit. L'accès des membres passe par une demande validée (voir les demandes).
 */
class MarketingAccountController extends AdminController
{
    private const MODULE = 'marketing.accounts';

    public function index(Request $request): View
    {
        $user = $request->user();
        $reach = MarketingEntities::reach($user);
        $entity = $reach->has((string) $request->query('entity')) ? (string) $request->query('entity') : null;
        $type = array_key_exists((string) $request->query('type'), MarketingEntities::TYPES) ? (string) $request->query('type') : null;
        $onlyMissing = $request->boolean('missing');
        $needle = trim(mb_substr((string) $request->query('q', ''), 0, 80));

        $accounts = MarketingEntities::restrict(MarketingAccount::query(), $user)
            ->orderBy('platform')->orderBy('name')->get()
            ->groupBy(fn (MarketingAccount $account): string => $account->entityKey());

        // Une ligne par entité : l'état de ses pages Facebook et Instagram, avec les accès déposés, et ses autres accès.
        $rows = $reach
            ->when($type, fn ($entities) => $entities->where('type', $type))
            ->when($entity, fn ($entities) => $entities->where('key', $entity))
            ->map(function (array $item) use ($accounts): array {
                $owned = $accounts[$item['key']] ?? collect();
                $cells = [];

                foreach (MarketingAccount::REQUIRED as $key) {
                    $list = $owned->where('platform', $key)->values();
                    $cells[$key] = [
                        'accounts' => $list,
                        'state' => $list->contains(fn (MarketingAccount $row): bool => filled($row->profile_url))
                            ? ($list->contains(fn (MarketingAccount $row): bool => $row->hasPassword()) ? 'complete' : 'page')
                            : 'none',
                    ];
                }

                return ['entity' => $item, 'cells' => $cells, 'others' => $owned->whereNotIn('platform', MarketingAccount::REQUIRED)->values()];
            })
            ->values();

        $missing = $rows->sum(fn (array $row): int => collect($row['cells'])->where('state', 'none')->count());

        if ($onlyMissing) {
            $rows = $rows->filter(fn (array $row): bool => collect($row['cells'])->contains('state', 'none'))->values();
        }

        if ($needle !== '') {
            $lower = mb_strtolower($needle);
            $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['entity']['label']), $lower)
                || collect($row['cells'])->pluck('accounts')->push($row['others'])->flatten()
                    ->contains(fn (MarketingAccount $account): bool => str_contains(mb_strtolower($account->name.' '.$account->login), $lower)))->values();
        }

        $page = max(1, (int) $request->query('page', 1));
        $entities = (new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, ['path' => $request->url(), 'pageName' => 'page']))->withQueryString();

        return view('admin.marketing.index', [
            'entities' => $entities,
            'missing' => $missing,
            'filters' => ['entity' => $entity, 'type' => $type, 'missing' => $onlyMissing, 'q' => $needle],
            'canCreate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['create']),
            'canUpdate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['update']),
            'canDelete' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['delete']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        $platform = array_key_exists((string) $request->query('platform'), MarketingAccount::PLATFORMS) ? (string) $request->query('platform') : 'facebook';
        $entity = (string) ($request->query('entity') ?: ($request->integer('club') ? 'club:'.$request->integer('club') : ''));
        $account = new MarketingAccount(['platform' => $platform, 'two_factor' => 'none']);

        if (MarketingEntities::reach($request->user())->has($entity)) {
            $parsed = MarketingEntities::find($entity);
            $account->club_id = $parsed['club_id'];
            $account->scope = $parsed['scope'];
        }

        return view('admin.marketing.form', $this->formData($request, $account));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        $data = $this->validated($request);

        $account = MarketingAccount::query()->create($data + [
            'password_changed_at' => filled($data['password'] ?? null) ? now() : null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit($request, 'admin.marketing.account.created', $account, $this->trace($account));

        return redirect()->route('admin.marketing.accounts.index')->with('status', 'Page '.$account->platformLabel().' « '.$account->name.' » enregistrée pour '.$account->entityLabel().'.');
    }

    public function edit(Request $request, MarketingAccount $account): View
    {
        $this->authorizeAccount($request, $account);

        return view('admin.marketing.form', $this->formData($request, $account));
    }

    public function update(Request $request, MarketingAccount $account): RedirectResponse
    {
        $this->authorizeAccount($request, $account);

        $data = $this->validated($request);
        $passwordChanged = filled($data['password'] ?? null);

        // Mot de passe et codes de secours laissés vides conservent la valeur actuelle : le champ ne ré-affiche jamais le secret.
        if (! $passwordChanged) {
            unset($data['password']);
        }

        if (blank($data['recovery_codes'] ?? null)) {
            unset($data['recovery_codes']);
        }

        $account->fill($data + ['updated_by' => $request->user()->id]);

        if ($passwordChanged) {
            $account->password_changed_at = now();
        }

        $changed = array_values(array_diff(array_keys($account->getDirty()), ['password', 'recovery_codes', 'updated_by', 'password_changed_at', 'updated_at']));
        $account->save();

        $this->audit($request, 'admin.marketing.account.updated', $account, $this->trace($account) + ['password_changed' => $passwordChanged, 'fields' => $changed]);

        return redirect()->route('admin.marketing.accounts.index')->with('status', 'Accès « '.$account->name.' » mis à jour.');
    }

    public function destroy(Request $request, MarketingAccount $account): RedirectResponse
    {
        $this->authorizeAccount($request, $account);

        $this->audit($request, 'admin.marketing.account.deleted', $account, $this->trace($account));
        $name = $account->name;
        $account->delete();

        return redirect()->route('admin.marketing.accounts.index')->with('status', 'Accès « '.$name.' » retiré du coffre.');
    }

    /** Révèle les secrets d'un accès (JSON, jamais mis en cache) : chaque consultation est tracée. */
    public function secret(Request $request, MarketingAccount $account): JsonResponse
    {
        $this->authorizeAccount($request, $account);

        try {
            $payload = ['login' => $account->login, 'password' => $account->password, 'recovery_codes' => $account->recovery_codes];
        } catch (DecryptException) {
            return response()->json(['message' => 'Ce secret ne peut pas être déchiffré (clé de l’application modifiée) : saisissez-le à nouveau.'], 422);
        }

        $this->audit($request, 'admin.marketing.account.revealed', $account, $this->trace($account));

        return response()->json($payload)->header('Cache-Control', 'no-store, private');
    }

    private function authorizeAccount(Request $request, MarketingAccount $account): void
    {
        abort_unless(MarketingEntities::allows($request->user(), $account->club_id, $account->scope), 403, 'Cet élément est hors de votre périmètre.');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, MarketingAccount $account): array
    {
        return [
            'account' => $account,
            'entityOptions' => MarketingEntities::grouped(MarketingEntities::reach($request->user())),
            'entityValue' => $account->club_id !== null || $account->scope !== null ? $account->entityKey() : null,
            'platforms' => MarketingAccount::PLATFORMS,
            'twoFactor' => MarketingAccount::TWO_FACTOR,
            'hasPassword' => $account->exists && $account->hasPassword(),
            'hasCodes' => $account->exists && $account->getRawOriginal('recovery_codes') !== null,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $reach = MarketingEntities::reach($request->user());

        $data = $request->validate([
            'entity' => ['required', 'string', Rule::in($reach->keys()->all())],
            'platform' => ['required', Rule::in(array_keys(MarketingAccount::PLATFORMS))],
            'name' => ['required', 'string', 'max:160'],
            'login' => ['nullable', 'string', 'max:190', Rule::requiredIf($request->input('platform') === 'email')],
            'password' => ['nullable', 'string', 'max:255'],
            'profile_url' => ['nullable', 'url:http,https', 'max:255', Rule::requiredIf(in_array($request->input('platform'), MarketingAccount::REQUIRED, true))],
            'recovery_email' => ['nullable', 'email', 'max:190'],
            'recovery_phone' => ['nullable', 'string', 'max:40'],
            'two_factor' => ['required', Rule::in(array_keys(MarketingAccount::TWO_FACTOR))],
            'recovery_codes' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'entity.required' => 'Choisissez l’entité (Fondation, chapitre, projet ou club).',
            'entity.in' => 'Cette entité est hors de votre périmètre.',
            'profile_url.required' => 'Le lien de la page est obligatoire : il est affiché sur la page de l’entité.',
            'login.required' => 'L’identifiant est obligatoire pour une adresse e-mail.',
            'name.required' => 'Indiquez le nom de la page, du profil ou l’adresse e-mail.',
        ]);

        $entity = $reach[$data['entity']] ?? null;

        if ($entity === null) {
            throw ValidationException::withMessages(['entity' => 'Cette entité est hors de votre périmètre.']);
        }

        unset($data['entity']);
        $data['club_id'] = $entity['club_id'];
        $data['scope'] = $entity['scope'];

        return $data;
    }

    /** Traces d'audit : jamais de secret. */
    private function trace(MarketingAccount $account): array
    {
        return ['platform' => $account->platform, 'name' => $account->name, 'entity' => $account->entityKey()];
    }
}
