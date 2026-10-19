<?php

namespace App\Support\Legacy;

use App\Models\Club;
use App\Models\Entity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Migre `tbl_users` (ancienne plateforme) vers users + profiles + memberships.
 *
 * Choix de securite :
 *  - les mots de passe bcrypt ($2y$) sont conserves tels quels (pas de re-hachage) ;
 *  - AUCUN role d'administration n'est attribue : le systeme de droits est limite par
 *    entite, un « Président » legacy deviendrait administrateur de toute sa branche.
 *    Le role d'origine est conserve dans memberships.membership_type ;
 *  - les comptes exclus / demissionnaires / supprimes sont importes inactifs (connexion refusee) ;
 *  - le CIN est stocke chiffre.
 */
final class LegacyUserImporter
{
    private const ROLE_TYPES = [
        0 => 'member', 1 => 'president', 2 => 'coordinator', 3 => 'vice_president', 4 => 'responsible',
        5 => 'member', 6 => 'assistant', 7 => 'manager', 8 => 'project_manager', 9 => 'advisor',
        10 => 'executive_manager', 11 => 'tunifan',
    ];

    private const ROLE_LABELS = [
        0 => 'Membre', 1 => 'Président(e)', 2 => 'Coordinateur', 3 => 'VP', 4 => 'Responsable', 5 => 'Membre',
        6 => 'Assistant(e)', 7 => 'Manager', 8 => 'Project Manager', 9 => 'Conseiller',
        10 => 'Executif manager', 11 => 'TuniFan',
    ];

    private const BRANCHES = [-1 => 'foundation', 0 => 'university', 1 => 'high-school', 2 => 'alumni'];

    private const CELLS = [
        'evenmentiel' => 'Événementiel',
        'evenementiel' => 'Événementiel',
        'ressource humaine' => 'Ressources humaines',
        'ressources humaines' => 'Ressources humaines',
    ];

    private const PLACEHOLDER_DOMAIN = 'legacy.invalid';

    private const FLUSH_SIZE = 500;

    /** @var array<string, int|array<string, mixed>> */
    private array $stats = [];

    /** @var array<int, array{0: int, 1: string, 2: string}> */
    private array $report = [];

    /** @var array<int, int> legacy_id => user id */
    private array $usersByLegacy = [];

    /** @var array<string, array{id: int, legacy_id: int|null}> */
    private array $usersByEmail = [];

    /** @var array<int, array{club: int, entity: int}> */
    private array $clubs = [];

    /** @var array<string, int> */
    private array $entities = [];

    /** @var array<int, array<string, mixed>> */
    private array $profileBuffer = [];

    /** @var array<int, array<string, mixed>> */
    private array $membershipBuffer = [];

    private GovernorateMatcher $governorates;

    private string $now;

    /**
     * @return array{stats: array<string, int>, report: array<int, array{0: int, 1: string, 2: string}>}
     */
    public function import(string $path, bool $dryRun = false): array
    {
        $this->governorates = new GovernorateMatcher();
        $this->now = now()->format('Y-m-d H:i:s');
        $this->stats = [
            'rows' => 0, 'users' => 0, 'created' => 0, 'updated' => 0, 'linked' => 0, 'merged_rows' => 0,
            'inactive' => 0, 'emails_repaired' => 0, 'emails_placeholder' => 0, 'memberships' => 0,
            'avatars' => 0, 'skipped_clubs' => 0,
        ];
        $this->report = [];
        $this->profileBuffer = [];
        $this->membershipBuffer = [];

        $groups = $this->readGroups($path);
        $this->preload();

        DB::beginTransaction();

        try {
            foreach ($groups as $group) {
                $this->importGroup($group);
            }
            $this->flush();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return ['stats' => $this->stats, 'report' => $this->report];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function readGroups(string $path): array
    {
        $groups = [];

        foreach (SqlDumpReader::rows($path, 'tbl_users') as $row) {
            $this->stats['rows']++;
            $legacyId = (int) $row['userId'];

            [$email, $state] = $this->resolveEmail((string) $row['email'], $legacyId);
            $row['_email'] = $email;
            $row['_email_state'] = $state;
            $row['_original_email'] = trim((string) $row['email']);
            $row['_inactive'] = (int) ($row['isDeleted'] === '1' || trim((string) $row['blockText']) !== '');

            $groups[$email][] = $row;
        }

        if ($groups === []) {
            throw new \RuntimeException('Aucune ligne `tbl_users` trouvee dans '.$path);
        }

        return $groups;
    }

    private function preload(): void
    {
        $this->usersByLegacy = [];
        $this->usersByEmail = [];

        foreach (DB::table('users')->get(['id', 'email', 'legacy_id']) as $user) {
            $this->usersByEmail[mb_strtolower($user->email)] = ['id' => (int) $user->id, 'legacy_id' => $user->legacy_id !== null ? (int) $user->legacy_id : null];
            if ($user->legacy_id !== null) {
                $this->usersByLegacy[(int) $user->legacy_id] = (int) $user->id;
            }
        }

        $this->entities = Entity::query()->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();
        $this->clubs = [];
        foreach (Club::query()->whereNotNull('legacy_id')->get(['id', 'legacy_id', 'entity_id']) as $club) {
            $this->clubs[(int) $club->legacy_id] = ['club' => (int) $club->id, 'entity' => (int) $club->entity_id];
        }
    }

    /** @param array<int, array<string, mixed>> $group */
    private function importGroup(array $group): void
    {
        // Le compte « principal » : actif de preference, puis le plus recemment mis a jour.
        $rank = fn (array $row): array => [
            $row['_inactive'],
            -max($this->timestamp($row['updatedDtm'] ?? null), $this->timestamp($row['createdDtm'])),
            -(int) $row['userId'],
        ];
        usort($group, fn (array $a, array $b): int => $rank($a) <=> $rank($b));

        $primary = $group[0];
        $groupIds = array_map(fn (array $row): int => (int) $row['userId'], $group);

        if (count($group) > 1) {
            $this->stats['merged_rows'] += count($group) - 1;
            $this->note((int) $primary['userId'], 'duplicate_merged', 'ids '.implode(', ', $groupIds).' fusionnes dans '.$primary['userId']);
        }

        foreach ($group as $row) {
            $this->noteRow($row);
        }

        [$userId, $created] = $this->syncUser($primary, $groupIds);
        $profile = $this->profileData($primary);

        if ($created) {
            $this->queueProfile($userId, $profile);
            $existingMemberships = [];
        } else {
            $this->fillProfile($userId, $profile);
            $existingMemberships = DB::table('memberships')->where('user_id', $userId)->get(['entity_id', 'club_id'])
                ->mapWithKeys(fn ($m) => [$m->entity_id.'|'.($m->club_id ?? '0') => true])->all();
        }

        foreach ($group as $row) {
            $this->syncMembership($userId, $row, $existingMemberships);
        }

        if (count($this->profileBuffer) >= self::FLUSH_SIZE || count($this->membershipBuffer) >= self::FLUSH_SIZE) {
            $this->flush();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $groupIds
     * @return array{0: int, 1: bool} [id utilisateur, cree ?]
     */
    private function syncUser(array $row, array $groupIds): array
    {
        $legacyId = (int) $row['userId'];
        $email = $row['_email'];
        $this->stats['users']++;

        $userId = $this->usersByLegacy[$legacyId] ?? null;

        if ($userId === null) {
            foreach ($groupIds as $id) {
                $userId ??= $this->usersByLegacy[$id] ?? null;
            }
        }

        if ($userId === null && isset($this->usersByEmail[$email])) {
            $hit = $this->usersByEmail[$email];

            if ($hit['legacy_id'] === null) {
                // Compte deja cree dans la nouvelle plateforme avec le meme e-mail : on le rattache.
                $userId = $hit['id'];
                DB::table('users')->where('id', $userId)->update(['legacy_id' => $legacyId]);
                $this->usersByLegacy[$legacyId] = $userId;
                $this->usersByEmail[$email]['legacy_id'] = $legacyId;
                $this->stats['linked']++;
                $this->note($legacyId, 'linked_existing_account', 'compte existant #'.$userId.' rattache (mot de passe conserve)');
            } else {
                $email = $this->placeholder($legacyId);
            }
        }

        if ($userId !== null) {
            $this->stats['updated']++;

            return [$userId, false];
        }

        $password = (string) $row['password'];
        if (! preg_match('/^\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}$/', $password)) {
            $password = Hash::make(Str::random(48));
            $this->note($legacyId, 'password_invalid', 'mot de passe illisible : compte a reinitialiser');
        }

        $inactive = (bool) $row['_inactive'];
        $createdAt = $this->datetime($row['createdDtm']) ?? $this->now;
        $updatedAt = $this->datetime($row['updatedDtm'] ?? null) ?? $createdAt;

        $userId = (int) DB::table('users')->insertGetId([
            'legacy_id' => $legacyId,
            'name' => Str::limit($this->displayName($row, $email), 255, ''),
            'email' => $email,
            'email_verified_at' => $this->datetime($row['mailValid']),
            'password' => $password,
            'is_active' => ! $inactive,
            'deactivated_reason' => $inactive ? $this->deactivationReason($row) : null,
            'deactivated_at' => $inactive ? $updatedAt : null,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        $this->usersByLegacy[$legacyId] = $userId;
        $this->usersByEmail[$email] = ['id' => $userId, 'legacy_id' => $legacyId];
        $this->stats['created']++;
        $this->stats['inactive'] += $inactive ? 1 : 0;

        return [$userId, true];
    }

    /** @param array<string, mixed> $row */
    private function profileData(array $row): array
    {
        $roleId = (int) $row['roleId'];
        $headline = implode(' · ', array_filter([
            self::ROLE_LABELS[$roleId] ?? null,
            $this->cell((string) $row['cellule']),
        ]));

        $cin = trim((string) $row['cin']);
        $about = trim((string) $row['about']);
        $address = trim((string) $row['adresse']);
        $delegation = trim((string) $row['delegation']);

        return [
            'headline' => $headline !== '' ? Str::limit($headline, 160, '') : null,
            'phone' => $this->phone((string) $row['mobile']),
            'phone_secondary' => $this->phone((string) $row['mobile2']),
            'gender' => match (mb_strtolower(trim((string) $row['sexe']))) {
                'homme' => 'male',
                'femme' => 'female',
                default => null,
            },
            'birth_date' => $this->date($row['birthday']),
            'address' => $address !== '' ? $address : null,
            'governorate_id' => $this->governorates->find((string) $row['gouvernorat'])?->id,
            'delegation' => $delegation !== '' ? Str::limit($delegation, 255, '') : null,
            'cin' => $cin !== '' ? Crypt::encryptString($cin) : null,
            'facebook_url' => $this->url($row['facebook']),
            'instagram_url' => $this->url($row['instagram']),
            'linkedin_url' => $this->url($row['linkedin']),
            'bio' => $about !== '' ? $about : null,
            'photo_path' => $this->avatar((string) $row['avatar']),
        ];
    }

    /** @param array<string, mixed> $profile */
    private function queueProfile(int $userId, array $profile): void
    {
        $this->profileBuffer[] = ['user_id' => $userId] + $profile + ['created_at' => $this->now, 'updated_at' => $this->now];
    }

    /** Utilisateur deja present : on ne complete que les champs encore vides (jamais d'ecrasement). */
    private function fillProfile(int $userId, array $profile): void
    {
        $current = DB::table('profiles')->where('user_id', $userId)->first();

        if ($current === null) {
            $this->queueProfile($userId, $profile);

            return;
        }

        $updates = [];
        foreach ($profile as $column => $value) {
            if ($value !== null && ($current->{$column} === null || $current->{$column} === '')) {
                $updates[$column] = $value;
            }
        }

        if ($updates !== []) {
            DB::table('profiles')->where('user_id', $userId)->update($updates + ['updated_at' => $this->now]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, bool>  $existing
     */
    private function syncMembership(int $userId, array $row, array &$existing): void
    {
        $legacyClub = (int) $row['ClubID'];

        if (isset($this->clubs[$legacyClub])) {
            $entityId = $this->clubs[$legacyClub]['entity'];
            $clubId = $this->clubs[$legacyClub]['club'];
        } elseif (isset(self::BRANCHES[$legacyClub], $this->entities[self::BRANCHES[$legacyClub]])) {
            $entityId = $this->entities[self::BRANCHES[$legacyClub]];
            $clubId = null;
        } else {
            $this->stats['skipped_clubs']++;
            $this->note((int) $row['userId'], 'club_unknown', 'ClubID '.$legacyClub.' introuvable (importer d\'abord les clubs)');

            return;
        }

        $key = $entityId.'|'.($clubId ?? '0');
        if (isset($existing[$key])) {
            return;
        }
        $existing[$key] = true;

        $inactive = (bool) $row['_inactive'];
        $created = $this->datetime($row['createdDtm']);
        $updated = $this->datetime($row['updatedDtm'] ?? null);

        $this->membershipBuffer[] = [
            'user_id' => $userId,
            'entity_id' => $entityId,
            'department_id' => null,
            'club_id' => $clubId,
            'status' => $inactive ? 'archived' : 'approved',
            'membership_type' => self::ROLE_TYPES[(int) $row['roleId']] ?? 'member',
            'starts_at' => $created ? substr($created, 0, 10) : null,
            'ends_at' => $inactive && $updated ? substr($updated, 0, 10) : null,
            'approved_by' => null,
            'approved_at' => $inactive ? null : $created,
            'rejection_reason' => null,
            'created_at' => $created ?? $this->now,
            'updated_at' => $updated ?? $created ?? $this->now,
        ];
        $this->stats['memberships']++;
    }

    private function flush(): void
    {
        foreach (array_chunk($this->profileBuffer, self::FLUSH_SIZE) as $chunk) {
            DB::table('profiles')->insert($chunk);
        }
        foreach (array_chunk($this->membershipBuffer, self::FLUSH_SIZE) as $chunk) {
            DB::table('memberships')->insert($chunk);
        }

        $this->profileBuffer = [];
        $this->membershipBuffer = [];
    }

    /** @param array<string, mixed> $row */
    private function noteRow(array $row): void
    {
        $legacyId = (int) $row['userId'];

        if ($row['_email_state'] === 'repaired') {
            $this->stats['emails_repaired']++;
            $this->note($legacyId, 'email_repaired', $row['_original_email'].' -> '.$row['_email']);
        } elseif ($row['_email_state'] === 'placeholder') {
            $this->stats['emails_placeholder']++;
            $this->note($legacyId, 'email_placeholder', 'e-mail invalide ou absent ("'.$row['_original_email'].'") : a corriger');
        }

        if ((int) $row['SA'] > 0) {
            $this->note($legacyId, 'legacy_super_admin', 'SA='.$row['SA'].' : aucun droit d\'administration attribue automatiquement');
        }
    }

    private function note(int $legacyId, string $issue, string $detail): void
    {
        $this->report[] = [$legacyId, $issue, $detail];
    }

    /** @return array{0: string, 1: string} [e-mail, ok|repaired|placeholder] */
    private function resolveEmail(string $raw, int $legacyId): array
    {
        $original = mb_strtolower(trim($raw));

        if ($original === '') {
            return [$this->placeholder($legacyId), 'placeholder'];
        }

        $clean = preg_replace('/\s+/u', '', $original) ?? $original;
        $clean = rtrim($clean, '.,;');
        $clean = preg_replace('/@(gmail|hotmail|outlook|yahoo)$/', '@$1.com', $clean) ?? $clean;

        if (strlen($clean) <= 255 && filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            return [$clean, $clean === $original ? 'ok' : 'repaired'];
        }

        return [$this->placeholder($legacyId), 'placeholder'];
    }

    private function placeholder(int $legacyId): string
    {
        return 'legacy-'.$legacyId.'@'.self::PLACEHOLDER_DOMAIN;
    }

    /** @param array<string, mixed> $row */
    private function displayName(array $row, string $email): string
    {
        $name = trim((string) $row['name']);
        if ($name === '') {
            $name = trim(trim((string) $row['prenom']).' '.trim((string) $row['nom']));
        }

        return $name !== '' ? $name : Str::before($email, '@');
    }

    /** @param array<string, mixed> $row */
    private function deactivationReason(array $row): string
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', (string) $row['blockText']));

        return $reason !== '' ? $reason : 'Compte supprime dans l\'ancienne plateforme.';
    }

    private function cell(string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : (self::CELLS[mb_strtolower($value)] ?? $value);
    }

    private function phone(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if ($digits === '' || (int) $digits === 0) {
            return null;
        }

        return strlen($digits) === 8 ? '+216'.$digits : Str::limit($digits, 20, '');
    }

    private function url(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('#^https?://#i', $value) ? Str::limit($value, 500, '') : null;
    }

    private function date(?string $value): ?string
    {
        if ($value === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return ((int) $m[1] >= 1900 && (int) $m[1] <= (int) date('Y')) ? $value : null;
    }

    private function datetime(?string $value): ?string
    {
        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) || $this->date(substr($value, 0, 10)) === null) {
            return null;
        }

        return $value;
    }

    private function timestamp(?string $value): int
    {
        $datetime = $this->datetime($value);

        return $datetime ? Carbon::parse($datetime)->getTimestamp() : 0;
    }

    /** Le fichier doit avoir ete copie dans storage/app/public/legacy-avatars/ ; sinon pas d'avatar. */
    private function avatar(string $file): ?string
    {
        $file = trim($file);

        if ($file === '' || $file === 'usersDefault.jpg' || $file !== basename($file) || str_contains($file, '..') || strlen($file) > 200) {
            return null;
        }

        $path = 'legacy-avatars/'.$file;
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $this->stats['avatars']++;

        return $path;
    }
}
