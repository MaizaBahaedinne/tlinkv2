<?php

namespace App\Http\Controllers\Admin;

use App\Models\MarketingAccount;
use App\Support\ClubReach;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Coffre des accès marketing partagés (Facebook, Instagram, e-mail). Chaque club doit déposer ses accès Facebook et
 * Instagram : le tableau de conformité signale ceux qui manquent. Les secrets sont chiffrés, jamais affichés dans les
 * listes, et chaque consultation d'un secret est inscrite au journal d'audit.
 */
class MarketingAccountController extends AdminController
{
    private const MODULE = 'marketing.accounts';

    public function index(Request $request): View
    {
        $user = $request->user();
        $platform = array_key_exists((string) $request->query('platform'), MarketingAccount::PLATFORMS) ? (string) $request->query('platform') : null;
        $club = $request->integer('club') ?: null;
        $needle = trim(mb_substr((string) $request->query('q', ''), 0, 80));

        $accounts = ClubReach::scope(MarketingAccount::query(), $user)
            ->with(['club:id,title', 'updater:id,name'])
            ->when($platform, fn ($query) => $query->where('platform', $platform))
            ->when($club, fn ($query) => $query->where('club_id', $club))
            ->when($needle !== '', function ($query) use ($needle): void {
                $like = '%'.addcslashes($needle, '\\%_').'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('login', 'like', $like));
            })
            ->orderBy('club_id')->orderBy('platform')->orderBy('name')
            ->paginate(20)->withQueryString();

        $clubs = ClubReach::clubs($user);
        $present = MarketingAccount::query()->whereIn('club_id', $clubs->pluck('id'))->get(['club_id', 'platform'])
            ->groupBy('club_id')->map(fn ($rows) => $rows->pluck('platform')->unique()->all());

        $compliance = $clubs->map(fn ($item) => [
            'club' => $item,
            'platforms' => collect(MarketingAccount::PLATFORMS)->map(fn ($label, $key): bool => in_array($key, $present[$item->id] ?? [], true))->all(),
        ]);

        $missing = $compliance->sum(fn (array $row): int => collect(MarketingAccount::REQUIRED)->reject(fn (string $key): bool => $row['platforms'][$key])->count());

        return view('admin.marketing.index', [
            'accounts' => $accounts,
            'compliance' => $compliance,
            'missing' => $missing,
            'clubOptions' => ClubReach::options($user),
            'filters' => ['platform' => $platform, 'club' => $club, 'q' => $needle],
            'canCreate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['create']),
            'canUpdate' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['update']),
            'canDelete' => $this->context($request)->allowsModule(['event.manage'], self::MODULE, ['delete']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        $club = $request->integer('club') ?: null;
        $platform = array_key_exists((string) $request->query('platform'), MarketingAccount::PLATFORMS) ? (string) $request->query('platform') : 'facebook';

        return view('admin.marketing.form', $this->formData($request, new MarketingAccount(['club_id' => $club, 'platform' => $platform, 'two_factor' => 'none'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireModuleAction($request, self::MODULE, 'create');

        $data = $this->validated($request, null);
        ClubReach::validate($request->user(), $data['club_id'] ?? null);

        $account = MarketingAccount::query()->create($data + [
            'password_changed_at' => now(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->audit($request, 'admin.marketing.account.created', $account, $this->trace($account));

        return redirect()->route('admin.marketing.accounts.index')->with('status', 'Accès '.$account->platformLabel().' « '.$account->name.' » enregistré dans le coffre.');
    }

    public function edit(Request $request, MarketingAccount $account): View
    {
        ClubReach::authorize($request->user(), $account->club_id);

        return view('admin.marketing.form', $this->formData($request, $account));
    }

    public function update(Request $request, MarketingAccount $account): RedirectResponse
    {
        ClubReach::authorize($request->user(), $account->club_id);

        $data = $this->validated($request, $account);
        ClubReach::validate($request->user(), $data['club_id'] ?? null);

        $passwordChanged = array_key_exists('password', $data) && $data['password'] !== null && $data['password'] !== '';

        if (! $passwordChanged) {
            unset($data['password']);
        }

        // Les codes de secours laissés vides conservent la valeur actuelle : le champ ne ré-affiche jamais le secret.
        if (($data['recovery_codes'] ?? null) === null || $data['recovery_codes'] === '') {
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
        ClubReach::authorize($request->user(), $account->club_id);

        $this->audit($request, 'admin.marketing.account.deleted', $account, $this->trace($account));
        $name = $account->name;
        $account->delete();

        return redirect()->route('admin.marketing.accounts.index')->with('status', 'Accès « '.$name.' » retiré du coffre.');
    }

    /** Révèle les secrets d'un accès (JSON, jamais mis en cache) : chaque consultation est tracée. */
    public function secret(Request $request, MarketingAccount $account): JsonResponse
    {
        ClubReach::authorize($request->user(), $account->club_id);

        try {
            $payload = ['login' => $account->login, 'password' => $account->password, 'recovery_codes' => $account->recovery_codes];
        } catch (DecryptException) {
            return response()->json(['message' => 'Ce secret ne peut pas être déchiffré (clé de l’application modifiée) : saisissez-le à nouveau.'], 422);
        }

        $this->audit($request, 'admin.marketing.account.revealed', $account, $this->trace($account));

        return response()->json($payload)->header('Cache-Control', 'no-store, private');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, MarketingAccount $account): array
    {
        return [
            'account' => $account,
            'clubOptions' => ClubReach::options($request->user()),
            'canBeFoundation' => ClubReach::isGlobal($request->user()),
            'platforms' => MarketingAccount::PLATFORMS,
            'twoFactor' => MarketingAccount::TWO_FACTOR,
            'hasPassword' => $account->exists,
            'hasCodes' => $account->exists && $account->getRawOriginal('recovery_codes') !== null,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?MarketingAccount $account): array
    {
        $data = $request->validate([
            'club_id' => ['nullable', 'integer', Rule::exists('org_nodes', 'id')->where('kind', 'club')],
            'platform' => ['required', Rule::in(array_keys(MarketingAccount::PLATFORMS))],
            'name' => ['required', 'string', 'max:160'],
            'login' => ['required', 'string', 'max:190'],
            'password' => [$account === null ? 'required' : 'nullable', 'string', 'max:255'],
            'profile_url' => ['nullable', 'url:http,https', 'max:255'],
            'recovery_email' => ['nullable', 'email', 'max:190'],
            'recovery_phone' => ['nullable', 'string', 'max:40'],
            'two_factor' => ['required', Rule::in(array_keys(MarketingAccount::TWO_FACTOR))],
            'recovery_codes' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'password.required' => 'Le mot de passe est obligatoire : sans lui, l’équipe ne peut pas reprendre la main sur le compte.',
            'login.required' => 'L’identifiant de connexion est obligatoire.',
            'name.required' => 'Indiquez le nom de la page, du profil ou l’adresse e-mail.',
        ]);

        $data['club_id'] = ($data['club_id'] ?? null) ?: null;

        return $data;
    }

    /** Traces d'audit : jamais de secret. */
    private function trace(MarketingAccount $account): array
    {
        return ['platform' => $account->platform, 'name' => $account->name, 'club_id' => $account->club_id];
    }
}
