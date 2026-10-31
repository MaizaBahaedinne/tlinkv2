<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\IdentityVerification;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function documents(): array
    {
        return [
            'id_front' => UploadedFile::fake()->image('recto.jpg', 1000, 650),
            'id_back' => UploadedFile::fake()->image('verso.jpg', 1000, 650),
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 800, 800),
            'cin_number' => '07654321',
            'card_issued_on' => '2019-05-14',
            'card_issued_place' => 'Sfax',
            'consent' => '1',
        ];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $this->grantPlatformAdmin($admin);

        return $admin;
    }

    private function submitted(User $member): IdentityVerification
    {
        $this->actingAs($member)->post('/profil/verification', $this->documents())->assertSessionHasNoErrors();

        return IdentityVerification::query()->where('user_id', $member->id)->firstOrFail();
    }

    public function test_member_submits_documents_stored_encrypted_on_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $member = User::factory()->create();

        $verification = $this->submitted($member);

        $this->assertSame('pending', $verification->status);
        foreach (IdentityVerification::DOCUMENTS as $column) {
            $path = $verification->getRawOriginal($column);
            Storage::disk('local')->assertExists($path);
            Storage::disk('public')->assertMissing($path);
            $this->assertStringStartsNotWith("\xFF\xD8", Storage::disk('local')->get($path), 'la pièce ne doit pas être stockée en clair');
        }

        $this->get('/profil/verification')->assertOk()->assertSee('en cours d’examen');
        $this->get('/profil')->assertOk()->assertSee('Vérification en cours')->assertDontSee('Profil vérifié');
    }

    public function test_cannot_submit_twice_while_pending(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();
        $this->submitted($member);

        $this->post('/profil/verification', $this->documents())->assertRedirect(route('profile.verification'));

        $this->assertSame(1, IdentityVerification::query()->count());
    }

    public function test_documents_are_required_and_must_be_images(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();

        $this->actingAs($member)->post('/profil/verification', ['consent' => '1'])
            ->assertSessionHasErrors(['id_front', 'id_back', 'selfie', 'cin_number', 'card_issued_on', 'card_issued_place']);

        $this->post('/profil/verification', ['id_front' => UploadedFile::fake()->create('cin.pdf', 100, 'application/pdf')] + $this->documents())
            ->assertSessionHasErrors('id_front');

        $this->post('/profil/verification', ['selfie' => UploadedFile::fake()->image('petit.jpg', 100, 100)] + $this->documents())
            ->assertSessionHasErrors('selfie');

        $this->post('/profil/verification', ['consent' => null] + $this->documents())->assertSessionHasErrors('consent');

        $this->assertSame(0, IdentityVerification::query()->count());
    }

    public function test_page_offers_camera_capture_with_framing_guide_and_allows_camera_permission(): void
    {
        $member = User::factory()->create();

        $response = $this->actingAs($member)->get('/profil/verification');

        $response->assertOk()
            ->assertSee('id="camera-dialog"', false)
            ->assertSee('id="camera-guide"', false)
            ->assertSee('data-kind="card"', false)
            ->assertSee('data-kind="selfie"', false)
            ->assertSee('Prendre la photo')
            ->assertSee('getUserMedia', false);
        $this->assertStringContainsString('camera=(self)', (string) $response->headers->get('Permissions-Policy'));
        $this->assertStringNotContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
    }

    public function test_card_details_are_validated_stored_encrypted_shown_to_admin_then_erased(): void
    {
        Storage::fake('local');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $member = User::factory()->create();

        foreach ([['cin_number' => '1234567'], ['cin_number' => 'ABCDEFGH'], ['cin_number' => '123456789'], ['card_issued_on' => now()->addDay()->toDateString()], ['card_issued_on' => 'pas une date'], ['card_issued_place' => '']] as $bad) {
            $this->actingAs($member)->post('/profil/verification', $bad + $this->documents())->assertSessionHasErrors(array_key_first($bad));
        }
        $this->assertSame(0, IdentityVerification::query()->count());

        $verification = $this->submitted($member);
        $raw = \Illuminate\Support\Facades\DB::table('identity_verifications')->where('id', $verification->id)->first();
        $this->assertStringNotContainsString('07654321', (string) $raw->cin_number, 'numéro chiffré en base');
        $this->assertStringNotContainsString('Sfax', (string) $raw->card_issued_place);
        $this->assertSame('07654321', $verification->cin_number);

        $this->actingAs($this->admin())->get('/admin/verifications/'.$verification->id)
            ->assertOk()->assertSee('07654321')->assertSee('14/05/2019')->assertSee('Sfax');

        $this->post('/admin/verifications/'.$verification->id.'/valider');

        $raw = \Illuminate\Support\Facades\DB::table('identity_verifications')->where('id', $verification->id)->first();
        $this->assertNull($raw->cin_number);
        $this->assertNull($raw->card_issued_on);
        $this->assertNull($raw->card_issued_place);
    }

    public function test_module_is_in_catalog_and_menu_entry_is_visible_to_global_admin(): void
    {
        $this->assertTrue(\App\Models\AppModule::query()->where('key', 'members.verification')->where('is_active', true)->exists());

        $this->actingAs($this->admin())->get('/admin')->assertOk()
            ->assertSee('Vérifications d’identité')
            ->assertSee(route('admin.verifications.index'), false);
    }

    public function test_guest_cannot_access_verification(): void
    {
        $this->get('/profil/verification')->assertRedirect();
        $this->post('/profil/verification', $this->documents())->assertRedirect();
    }

    public function test_admin_approves_badge_appears_and_documents_are_deleted(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();
        $verification = $this->submitted($member);
        $paths = collect(IdentityVerification::DOCUMENTS)->map(fn ($column) => $verification->getRawOriginal($column))->all();

        $admin = $this->admin();
        $this->actingAs($admin);

        $this->get('/admin/verifications')->assertOk()->assertSee($member->name);
        $this->get('/admin/verifications/'.$verification->id)->assertOk()->assertSee('Valider le profil');

        $image = $this->get('/admin/verifications/'.$verification->id.'/front');
        $image->assertOk();
        $this->assertSame('image/jpeg', $image->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $image->headers->get('Cache-Control'));

        $this->post('/admin/verifications/'.$verification->id.'/valider')->assertRedirect(route('admin.verifications.index'));

        $verification->refresh();
        $this->assertSame('approved', $verification->status);
        $this->assertSame($admin->id, $verification->reviewed_by);
        $this->assertNull($verification->getRawOriginal('id_front_path'));
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
        $this->assertNotNull(Profile::query()->where('user_id', $member->id)->value('identity_verified_at'));
        $this->assertTrue(AppNotification::query()->where('user_id', $member->id)->where('type', 'identity.approved')->exists());

        $this->actingAs($member)->get('/profil')->assertOk()->assertSee('Profil vérifié');
        $this->get('/admin/verifications/'.$verification->id.'/front')->assertForbidden();
    }

    public function test_verified_badge_is_visible_on_public_member_profile(): void
    {
        $member = User::factory()->create(['name' => 'Salma Ben Ali']);
        Profile::query()->create(['user_id' => $member->id])->forceFill(['identity_verified_at' => now()])->save();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('member.show', $member))->assertOk()->assertSee('Profil vérifié');
    }

    public function test_admin_rejects_with_reason_and_member_can_retry(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();
        $verification = $this->submitted($member);

        $this->actingAs($this->admin());
        $this->post('/admin/verifications/'.$verification->id.'/refuser', ['reason' => ''])->assertSessionHasErrors('reason');

        $this->post('/admin/verifications/'.$verification->id.'/refuser', ['reason' => 'Le verso est flou.'])->assertRedirect();

        $verification->refresh();
        $this->assertSame('rejected', $verification->status);
        $this->assertNull(Profile::query()->where('user_id', $member->id)->value('identity_verified_at'));
        $this->assertNull($verification->getRawOriginal('selfie_path'));
        $this->assertTrue(AppNotification::query()->where('user_id', $member->id)->where('type', 'identity.rejected')->exists());

        $this->actingAs($member)->get('/profil/verification')->assertOk()->assertSee('Le verso est flou.')->assertSee('Envoyer pour vérification');
        $this->post('/profil/verification', $this->documents())->assertSessionHasNoErrors();
        $this->assertSame(2, IdentityVerification::query()->count());
    }

    public function test_regular_member_cannot_reach_admin_review_or_documents(): void
    {
        Storage::fake('local');
        $member = User::factory()->create();
        $verification = $this->submitted($member);

        $other = User::factory()->create();
        $this->actingAs($other);

        $this->get('/admin/verifications')->assertForbidden();
        $this->get('/admin/verifications/'.$verification->id.'/front')->assertForbidden();
        $this->post('/admin/verifications/'.$verification->id.'/valider')->assertForbidden();
        $this->assertSame('pending', $verification->fresh()->status);

        $this->actingAs($member)->get('/admin/verifications/'.$verification->id.'/selfie')->assertForbidden();
    }
}
