<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ErrorReport;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Pages d'erreur : explication claire, référence d'incident et rapport à transmettre à l'administrateur. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::get('/_erreur/{code}', fn (int $code) => abort($code))->whereNumber('code');
            Route::get('/_boom', fn () => throw new \RuntimeException('secret-sql-detail'));
        });
    }

    private function reference(string $html): string
    {
        $this->assertSame(1, preg_match('/ERR-\d{6}-[A-Z0-9]{6}/', $html, $match));

        return $match[0];
    }

    public function test_unknown_page_shows_explanation_reference_and_share_options(): void
    {
        $html = $this->get('/page-qui-nexiste-pas')
            ->assertNotFound()
            ->assertSee('Page introuvable')
            ->assertSee('Que s’est-il passé ?')
            ->assertSee('Que faire ?')
            ->assertSee('Transmettre l’incident à l’administrateur')
            ->assertSee('Copier le rapport')
            ->assertSee('wa.me', false)
            ->assertSee('/page-qui-nexiste-pas')
            ->getContent();

        $this->reference($html);
    }

    public function test_every_documented_code_has_its_own_explanation(): void
    {
        foreach (ErrorReport::CATALOG as $code => $info) {
            $this->get('/_erreur/'.$code)->assertStatus($code)->assertSee($info['title']);
        }

        $this->get('/_erreur/418')->assertStatus(418)->assertSee('Demande impossible (418)');
        $this->get('/_erreur/507')->assertStatus(507)->assertSee('Erreur du serveur (507)');
    }

    public function test_report_carries_the_admin_resolution_hint(): void
    {
        $this->get('/_erreur/403')->assertForbidden()->assertSee('Accès refusé')->assertSee('Piste de résolution')->assertSee('mandat actif');
        $this->get('/_erreur/419')->assertStatus(419)->assertSee('Session expirée')->assertSee('Réessayer');
        $this->get('/_erreur/401')->assertUnauthorized()->assertSee('Se connecter');
    }

    public function test_report_names_the_connected_member_and_never_the_query_string(): void
    {
        $user = User::factory()->create(['name' => 'Sami Trabelsi']);

        $this->actingAs($user)->get('/page-introuvable?token=secret-token-123')
            ->assertNotFound()
            ->assertSee('Sami Trabelsi')
            ->assertSee('n° '.$user->id)
            ->assertDontSee('secret-token-123');

        $this->flushSession();
        auth()->logout();
        $this->get('/page-introuvable')->assertSee('visiteur non connecté');
    }

    public function test_email_link_uses_the_contact_address_set_in_the_admin(): void
    {
        $this->get('/page-introuvable')->assertDontSee('href="mailto:', false);

        app(SiteSettings::class)->save(['contact_email' => 'support@tunivisions.tn']);

        $this->get('/page-introuvable')->assertSee('href="mailto:support@tunivisions.tn?subject=', false);
    }

    public function test_server_error_hides_technical_details_but_logs_the_reference(): void
    {
        config(['app.debug' => false]);
        Log::spy();

        $html = $this->get('/_boom')
            ->assertStatus(500)
            ->assertSee('Erreur du serveur')
            ->assertSee('storage/logs/laravel.log')
            ->assertDontSee('secret-sql-detail')
            ->getContent();

        $reference = $this->reference($html);

        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => ($context['error_reference'] ?? null) === $reference && ($context['error_path'] ?? null) === '/_boom')->once();
    }

    public function test_debug_mode_keeps_the_exception_page_for_developers(): void
    {
        config(['app.debug' => true]);

        $this->get('/_boom')->assertStatus(500)->assertSee('secret-sql-detail');
    }

    public function test_denied_access_is_logged_with_its_reference(): void
    {
        Log::spy();

        $html = $this->get('/_erreur/403')->getContent();
        $reference = $this->reference($html);

        Log::shouldHaveReceived('notice')->withArgs(fn ($message, $context = []) => ($context['error_reference'] ?? null) === $reference && ($context['status'] ?? null) === 403)->once();
    }

    public function test_api_errors_stay_json(): void
    {
        $this->getJson('/api/route-inconnue')->assertNotFound()->assertJsonStructure(['message'])->assertDontSee('Transmettre');
    }

    public function test_error_page_survives_a_failing_settings_lookup(): void
    {
        \Illuminate\Support\Facades\Schema::drop('settings');

        $this->get('/page-introuvable')->assertNotFound()->assertSee('Page introuvable')->assertDontSee('href="mailto:', false);
    }
}
