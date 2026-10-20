<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Entity;
use App\Models\Membership;
use App\Models\Profile;
use App\Models\User;
use App\Support\Legacy\LegacyUserImporter;
use App\Support\Legacy\SqlDumpReader;
use Database\Seeders\EntitySeeder;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegacyUserImportTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = [
        'userId', 'email', 'mailValid', 'password', 'name', 'nom', 'prenom', 'adresse', 'mobile', 'mobile2', 'roleId',
        'cellule', 'isDeleted', 'createdBy', 'createdDtm', 'updatedBy', 'updatedDtm', 'cin', 'sexe', 'birthday',
        'gouvernorat', 'delegation', 'affectedYear', 'ClubID', 'facebook', 'instagram', 'linkedin', 'about', 'avatar',
        'SA', 'blockText', 'id',
    ];

    private string $hash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hash = password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 10]);
        $this->seed([EntitySeeder::class, GovernorateSeeder::class]);

        foreach ([143 => 'university', 299 => 'high-school'] as $legacyId => $slug) {
            Club::query()->create([
                'name' => 'Club '.$legacyId,
                'slug' => 'legacy-'.$legacyId,
                'legacy_id' => $legacyId,
                'entity_id' => Entity::query()->where('slug', $slug)->value('id'),
                'status' => 'active',
            ]);
        }
    }

    private function sql(string $value): string
    {
        return "'".str_replace(['\\', "'", "\n"], ['\\\\', "\\'", '\\n'], $value)."'";
    }

    /** @param array<string, string|int> $overrides */
    private function row(array $overrides): string
    {
        $row = $overrides + [
            'email' => '', 'mailValid' => '0000-00-00 00:00:00', 'password' => $this->hash, 'name' => '', 'nom' => 'NOM',
            'prenom' => 'Prenom', 'adresse' => '', 'mobile' => '', 'mobile2' => 0, 'roleId' => 5, 'cellule' => '',
            'isDeleted' => 0, 'createdBy' => 2, 'createdDtm' => '2019-10-21 03:01:06', 'updatedBy' => 2,
            'updatedDtm' => '2020-11-23 16:17:26', 'cin' => '', 'sexe' => '', 'birthday' => '0000-00-00',
            'gouvernorat' => '', 'delegation' => '', 'affectedYear' => 0, 'ClubID' => 143, 'facebook' => '',
            'instagram' => '', 'linkedin' => '', 'about' => '', 'avatar' => 'usersDefault.jpg', 'SA' => 0,
            'blockText' => '', 'id' => 0,
        ];

        return '('.implode(', ', array_map(
            fn (string $column): string => is_int($row[$column]) ? (string) $row[$column] : $this->sql($row[$column]),
            self::COLUMNS
        )).')';
    }

    private function dump(): string
    {
        $rows = [
            $this->row(['userId' => 2, 'email' => 'maiza@example.test', 'name' => 'MAIZA Bahaedinne', 'roleId' => 7,
                'cellule' => 'Evenmentiel', 'cin' => '07209911', 'sexe' => 'Homme', 'birthday' => '1995-02-14',
                'gouvernorat' => 'Ben Arous', 'mobile' => '58465249', 'about' => "Ligne 1\nL'apostrophe", 'SA' => 1,
                'mailValid' => '2020-11-23 16:17:26', 'facebook' => 'https://www.facebook.com/x/', 'adresse' => '12 Rue A']),
            // Doublon d'e-mail : le compte actif et recent l'emporte, les deux adhesions sont conservees.
            $this->row(['userId' => 10, 'email' => 'Dup@example.test', 'name' => 'Dup Ancien', 'blockText' => 'démission',
                'ClubID' => 143, 'updatedDtm' => '2019-12-01 10:00:00', 'roleId' => 1]),
            $this->row(['userId' => 11, 'email' => 'dup@example.test ', 'name' => 'Dup Actif', 'ClubID' => 299,
                'updatedDtm' => '2021-01-01 10:00:00', 'roleId' => 5]),
            $this->row(['userId' => 20, 'email' => ' tunisien@gmail ', 'name' => 'Email Repare']),
            $this->row(['userId' => 21, 'email' => 'pasdearobase', 'name' => 'Email Invalide']),
            $this->row(['userId' => 30, 'email' => 'exist@example.test', 'name' => 'Deja Inscrit', 'mobile' => '99887766']),
            $this->row(['userId' => 40, 'email' => 'exclu@example.test', 'name' => 'Exclu', 'ClubID' => 1,
                'blockText' => "Exclu.\nCause : membre inactif"]),
            $this->row(['userId' => 50, 'email' => 'inconnu@example.test', 'name' => 'Club Inconnu', 'ClubID' => 9999]),
        ];

        $path = tempnam(sys_get_temp_dir(), 'users');
        file_put_contents($path, "INSERT INTO `tbl_users` (`".implode('`, `', self::COLUMNS)."`) VALUES\n".implode(",\n", $rows).";\n");

        return $path;
    }

    public function test_reader_handles_escapes_and_multiline_values(): void
    {
        $rows = iterator_to_array(SqlDumpReader::rows($this->dump(), 'tbl_users'), false);

        $this->assertCount(8, $rows);
        $this->assertSame("Ligne 1\nL'apostrophe", $rows[0]['about']);
        $this->assertSame("Exclu.\nCause : membre inactif", $rows[6]['blockText']);
        $this->assertSame('1', $rows[0]['SA']);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $result = app(LegacyUserImporter::class)->import($this->dump(), true);

        $this->assertSame(8, $result['stats']['rows']);
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Membership::query()->count());
    }

    public function test_import_creates_users_profiles_and_memberships_safely(): void
    {
        $existing = User::factory()->create(['email' => 'exist@example.test', 'password' => 'keep-this-one']);
        $existingHash = DB::table('users')->where('id', $existing->id)->value('password');
        $path = $this->dump();

        $result = app(LegacyUserImporter::class)->import($path);
        $stats = $result['stats'];

        // 8 lignes -> 7 comptes (1 doublon fusionne) dont 1 rattache a un compte existant.
        $this->assertSame(7, $stats['users']);
        $this->assertSame(1, $stats['merged_rows']);
        $this->assertSame(6, $stats['created']);
        $this->assertSame(1, $stats['linked']);
        $this->assertSame(7, User::query()->count());
        $this->assertSame(1, $stats['inactive']);
        $this->assertSame(1, $stats['emails_repaired']);
        $this->assertSame(1, $stats['emails_placeholder']);
        $this->assertSame(1, $stats['skipped_clubs']);

        // Mot de passe bcrypt conserve tel quel, et connexion possible.
        $maiza = User::query()->where('legacy_id', 2)->firstOrFail();
        $this->assertSame($this->hash, DB::table('users')->where('id', $maiza->id)->value('password'));
        $this->assertSame('MAIZA Bahaedinne', $maiza->name);
        $this->assertNotNull($maiza->email_verified_at);
        $this->assertTrue(Hash::check('secret123', $maiza->password));
        $this->post('/connexion', ['email' => 'maiza@example.test', 'password' => 'secret123'])->assertRedirect();
        $this->assertAuthenticatedAs($maiza);
        auth()->logout();

        // Compte existant : rattache, mot de passe intact, profil complete.
        $existing->refresh();
        $this->assertSame(30, $existing->legacy_id);
        $this->assertSame($existingHash, DB::table('users')->where('id', $existing->id)->value('password'));
        $this->assertSame('+21699887766', Profile::query()->where('user_id', $existing->id)->value('phone'));

        // Profil : champs convertis, CIN chiffre, aucune fuite en JSON.
        $profile = Profile::query()->where('user_id', $maiza->id)->firstOrFail();
        $this->assertSame('Manager · Événementiel', $profile->headline);
        $this->assertSame('+21658465249', $profile->phone);
        $this->assertSame('male', $profile->gender);
        $this->assertSame('1995-02-14', $profile->birth_date->toDateString());
        $this->assertSame('ben-arous', DB::table('governorates')->where('id', $profile->governorate_id)->value('slug'));
        $this->assertSame("Ligne 1\nL'apostrophe", $profile->bio);
        $this->assertSame('07209911', $profile->cin);
        $this->assertNotSame('07209911', DB::table('profiles')->where('id', $profile->id)->value('cin'));
        $this->assertArrayNotHasKey('cin', $profile->toArray());
        $this->assertNull(Profile::query()->where('user_id', User::query()->where('legacy_id', 11)->value('id'))->value('birth_date'));

        // Doublon : compte actif retenu, une adhesion par club, l'ancienne archivee.
        $dup = User::query()->where('legacy_id', 11)->firstOrFail();
        $this->assertSame('dup@example.test', $dup->email);
        $this->assertTrue($dup->is_active);
        $this->assertNull(User::query()->where('legacy_id', 10)->first());
        $memberships = Membership::query()->where('user_id', $dup->id)->get();
        $this->assertCount(2, $memberships);
        $old = $memberships->firstWhere('club_id', Club::query()->where('legacy_id', 143)->value('id'));
        $current = $memberships->firstWhere('club_id', Club::query()->where('legacy_id', 299)->value('id'));
        $this->assertSame(['archived', 'president'], [$old->status, $old->membership_type]);
        $this->assertSame(['approved', 'member'], [$current->status, $current->membership_type]);

        // E-mails corriges ou remplaces.
        $this->assertSame('tunisien@gmail.com', User::query()->where('legacy_id', 20)->value('email'));
        $this->assertSame('legacy-21@legacy.invalid', User::query()->where('legacy_id', 21)->value('email'));

        // Compte exclu : inactif, motif conserve, connexion refusee, adhesion archivee sur la branche.
        $excluded = User::query()->where('legacy_id', 40)->firstOrFail();
        $this->assertFalse($excluded->is_active);
        $this->assertSame('Exclu. Cause : membre inactif', $excluded->deactivated_reason);
        $this->post('/connexion', ['email' => 'exclu@example.test', 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $branch = Membership::query()->where('user_id', $excluded->id)->firstOrFail();
        $this->assertNull($branch->club_id);
        $this->assertSame('archived', $branch->status);
        $this->assertSame(Entity::query()->where('slug', 'high-school')->value('id'), $branch->entity_id);

        // Securite : aucun droit n'est attribue (meme au super admin legacy SA=1) : pas de poste dans l'organigramme.
        $this->assertSame(0, \App\Models\OrgAssignment::query()->count());
        $issues = collect($result['report'])->pluck(1)->all();
        $this->assertContains('legacy_super_admin', $issues);
        $this->assertContains('duplicate_merged', $issues);
        $this->assertContains('club_unknown', $issues);
    }

    public function test_import_is_idempotent_and_never_overwrites_new_data(): void
    {
        $path = $this->dump();
        app(LegacyUserImporter::class)->import($path);

        $maiza = User::query()->where('legacy_id', 2)->firstOrFail();
        $maiza->forceFill(['password' => 'nouveau-mot-de-passe'])->save();
        Profile::query()->where('user_id', $maiza->id)->update(['headline' => 'Titre modifie']);
        $users = User::query()->count();
        $memberships = Membership::query()->count();
        $profiles = Profile::query()->count();

        $again = app(LegacyUserImporter::class)->import($path)['stats'];

        $this->assertSame(0, $again['created']);
        $this->assertSame($users, User::query()->count());
        $this->assertSame($memberships, Membership::query()->count());
        $this->assertSame($profiles, Profile::query()->count());
        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $maiza->fresh()->password));
        $this->assertSame('Titre modifie', Profile::query()->where('user_id', $maiza->id)->value('headline'));
    }
}
