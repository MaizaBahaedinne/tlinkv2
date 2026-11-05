<?php

namespace Tests\Feature;

use App\Models\AppRelease;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Deployment;
use App\Support\ModuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class ReleasesAndDeploymentTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        if (is_file(storage_path('framework/down'))) {
            Artisan::call('up');
        }

        parent::tearDown();
    }

    private function globalAdmin(): User
    {
        $user = User::factory()->create();
        $this->grantAccess($user);

        return $user->fresh();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'version' => '1.4.0',
            'title' => 'Sous-domaines et simulateur',
            'summary' => 'Une grosse mise à jour.',
            'changes_new' => "- Simulateur de rôles\n* Sous-domaines",
            'changes_improvement' => '',
            'changes_fix' => "Correction du compte à rebours\n\n",
            'changes_security' => '',
            'status' => 'draft',
        ];
    }

    public function test_pages_are_reserved_to_global_administrators(): void
    {
        $this->get('/admin/versions')->assertRedirect('/connexion');

        $member = User::factory()->create();
        $this->actingAs($member)->get('/admin/versions')->assertForbidden();
        $this->actingAs($member)->get('/admin/deploiement')->assertForbidden();

        $scoped = User::factory()->create();
        $this->grantAccess($scoped, 'club_admin');
        $this->actingAs($scoped->fresh())->get('/admin/versions')->assertForbidden();
        $this->actingAs($scoped->fresh())->post('/admin/deploiement/caches')->assertForbidden();

        $admin = $this->globalAdmin();
        $this->actingAs($admin)->get('/admin/versions')->assertOk()->assertSee('Aucune version');
        $this->actingAs($admin)->get('/admin/deploiement')->assertOk()->assertSee('Migrations en attente');
    }

    public function test_menu_lists_both_pages_for_global_admin(): void
    {
        ModuleCatalog::sync();

        $this->actingAs($this->globalAdmin())->get('/admin')->assertOk()
            ->assertSee('Versions et changements')
            ->assertSee('Déploiement');
    }

    public function test_release_is_created_with_cleaned_changes_and_published(): void
    {
        $admin = $this->globalAdmin();

        $this->actingAs($admin)->post('/admin/versions', $this->payload())->assertRedirect('/admin/versions');

        $release = AppRelease::query()->firstOrFail();
        $this->assertSame('draft', $release->status);
        $this->assertNull($release->published_at);
        $this->assertSame(['new' => ['Simulateur de rôles', 'Sous-domaines'], 'fix' => ['Correction du compte à rebours']], $release->notes);
        $this->assertSame(3, $release->changeCount());
        $this->assertSame($admin->id, $release->author_id);

        $this->actingAs($admin)->patch('/admin/versions/'.$release->id.'/publication')->assertRedirect();
        $this->assertSame('published', $release->fresh()->status);
        $this->assertNotNull($release->fresh()->published_at);

        $this->actingAs($admin)->get('/admin/versions')->assertOk()->assertSee('1.4.0')->assertSee('Simulateur de rôles')->assertSee('Publiée');

        $this->actingAs($admin)->patch('/admin/versions/'.$release->id.'/publication');
        $this->assertSame('draft', $release->fresh()->status);

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.release.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.release.published']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.release.unpublished']);
    }

    public function test_publishing_through_the_form_sets_the_date(): void
    {
        $admin = $this->globalAdmin();

        $this->actingAs($admin)->post('/admin/versions', $this->payload(['status' => 'published']))->assertRedirect();

        $this->assertNotNull(AppRelease::query()->firstOrFail()->published_at);
    }

    public function test_version_must_be_valid_and_unique(): void
    {
        $admin = $this->globalAdmin();

        $this->actingAs($admin)->post('/admin/versions', $this->payload(['version' => 'bientot']))->assertSessionHasErrors('version');

        $this->actingAs($admin)->post('/admin/versions', $this->payload())->assertSessionDoesntHaveErrors();
        $this->actingAs($admin)->post('/admin/versions', $this->payload())->assertSessionHasErrors('version');

        $this->assertSame(1, AppRelease::query()->count());
    }

    public function test_release_can_be_edited_and_deleted(): void
    {
        $admin = $this->globalAdmin();
        $release = AppRelease::query()->create(['version' => '1.0.0', 'title' => 'Première', 'status' => 'draft', 'notes' => ['new' => ['Tout']]]);

        $this->actingAs($admin)->get('/admin/versions/'.$release->id.'/modifier')->assertOk()->assertSee('Tout');

        $this->actingAs($admin)->put('/admin/versions/'.$release->id, $this->payload(['version' => '1.0.0', 'title' => 'Renommée']))->assertRedirect('/admin/versions');
        $this->assertSame('Renommée', $release->fresh()->title);

        $this->actingAs($admin)->delete('/admin/versions/'.$release->id)->assertRedirect('/admin/versions');
        $this->assertSame(0, AppRelease::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.release.deleted')->count());
    }

    public function test_deployment_page_shows_release_info_and_pending_migrations(): void
    {
        file_put_contents(base_path('.release.json'), json_encode(['commit' => 'abcdef0123456789abcdef0123456789abcdef01', 'branch' => 'main', 'deployed_at' => '2026-11-05T10:00:00Z']));

        try {
            $info = Deployment::release();
            $this->assertSame('abcdef0123456789abcdef0123456789abcdef01', $info['commit']);
            $this->assertSame('main', $info['branch']);

            $this->actingAs($this->globalAdmin())->get('/admin/deploiement')->assertOk()->assertSee('abcdef01')->assertSee('Branche main');
        } finally {
            @unlink(base_path('.release.json'));
        }

        $this->assertSame([], Deployment::pendingMigrations());
    }

    public function test_release_file_with_invalid_commit_is_ignored(): void
    {
        file_put_contents(base_path('.release.json'), json_encode(['commit' => '<script>', 'branch' => 'main']));

        try {
            $this->assertNull(Deployment::release()['commit']);
        } finally {
            @unlink(base_path('.release.json'));
        }
    }

    public function test_admin_create_command_makes_a_global_administrator(): void
    {
        $this->artisan('admin:create', ['email' => 'Premier@Exemple.tn', '--name' => 'Premier Admin', '--password' => 'secret-pass-123'])
            ->expectsOutputToContain('Administrateur global : premier@exemple.tn')
            ->assertSuccessful();

        $user = User::query()->where('email', 'premier@exemple.tn')->firstOrFail();
        $this->assertNotNull($user->onboarding_completed_at);

        $this->actingAs($user)->get('/admin/versions')->assertOk();

        // Relancer la commande ne crée aucun doublon et ne touche pas au mot de passe.
        $this->artisan('admin:create', ['email' => 'premier@exemple.tn'])->expectsOutputToContain('mot de passe inchangé')->assertSuccessful();
        $this->assertSame(1, User::query()->where('email', 'premier@exemple.tn')->count());

        $this->artisan('admin:create', ['email' => 'pas-un-email'])->assertFailed();
    }

    public function test_caches_can_be_cleared_and_maintenance_toggled(): void
    {
        $admin = $this->globalAdmin();

        $this->actingAs($admin)->post('/admin/deploiement/caches')->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.deployment.caches_cleared']);

        $response = $this->actingAs($admin)->post('/admin/deploiement/maintenance');
        $this->assertTrue(app()->isDownForMaintenance() || is_file(storage_path('framework/down')));
        $secret = Deployment::maintenance()['secret'] ?? null;
        $this->assertNotEmpty($secret);
        $response->assertRedirect('/'.$secret);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.deployment.maintenance_on']);

        Artisan::call('up');
        $this->assertNull(Deployment::maintenance());
    }
}
