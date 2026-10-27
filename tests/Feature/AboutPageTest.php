<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use App\Models\User;
use App\Support\ModuleCatalog;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    public function test_about_page_renders_all_sections_in_each_locale(): void
    {
        foreach (['fr', 'en', 'ar'] as $locale) {
            $this->get("/{$locale}/a-propos")
                ->assertOk()
                ->assertSee('id="equipe"', false)
                ->assertSee('role="tablist"', false)
                ->assertSee('role="tabpanel"', false)
                ->assertSee('id="newsletter"', false)
                ->assertSee('class="mv-card', false)
                ->assertSee('value-card', false);
        }
    }

    public function test_about_page_shows_three_values_and_three_slogans(): void
    {
        foreach (['fr', 'en', 'ar'] as $locale) {
            $page = $this->get("/{$locale}/a-propos")->assertOk()->assertSee('Dream Big')->assertSee('Work Hard')->assertSee('Think Tunivisions');

            $this->assertSame(3, substr_count($page->getContent(), 'site-card value-card'));
            $this->assertSame(3, substr_count($page->getContent(), 'class="slogan-item"'));
        }
    }

    public function test_slogans_are_editable_with_french_fallback(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->get('/admin/site-public')->assertOk()->assertSee('name="slogan_2[fr]"', false);

        $this->actingAs($admin)->put('/admin/site-public', [
            'slogan_1' => ['fr' => 'Rêvez grand', 'en' => '', 'ar' => ''],
        ])->assertSessionHasNoErrors();

        $this->get('/fr/a-propos')->assertSee('Rêvez grand')->assertDontSee('Dream Big');
        $this->get('/en/a-propos')->assertSee('Rêvez grand'); // repli sur le français
    }

    public function test_president_section_appears_only_once_the_message_is_filled(): void
    {
        $this->get('/fr/a-propos')->assertOk()->assertDontSee('id="president"', false);

        Setting::query()->create([
            'group' => SiteSettings::GROUP,
            'key' => SiteSettings::KEY,
            'value' => json_encode([
                'president_message' => ['fr' => 'Ensemble, nous construisons demain.'],
                'president_name' => 'Amel Ben Salah',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->get('/fr/a-propos')
            ->assertOk()
            ->assertSee('id="president"', false)
            ->assertSee('Ensemble, nous construisons demain.')
            ->assertSee('Amel Ben Salah');
    }

    public function test_admin_can_save_president_fields_and_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->put('/admin/site-public', [
            'president_message' => ['fr' => 'Un message du président.', 'en' => '', 'ar' => ''],
            'president_name' => 'Karim Trabelsi',
            'president_photo_file' => UploadedFile::fake()->image('president.jpg', 400, 400),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/fr/a-propos')->assertSee('Un message du président.')->assertSee('Karim Trabelsi');
        $this->assertNotEmpty(Storage::disk('public')->allFiles('site/president'));
    }

    public function test_site_settings_admin_is_split_into_tabs_with_every_field(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $page = $this->actingAs($admin)->get('/admin/site-public')->assertOk()->assertSee('data-tabset', false);

        foreach (['home', 'about', 'president', 'contact'] as $tab) {
            $page->assertSee('id="tab-'.$tab.'"', false)->assertSee('aria-controls="tab-'.$tab.'"', false);
        }

        // Chaque champ reste dans le formulaire unique : un seul « Enregistrer » pour tous les onglets.
        foreach (array_keys(SiteSettings::LOCALIZED) as $key) {
            $page->assertSee('name="'.$key.'[fr]"', false);
        }

        foreach (['president_name', 'president_photo_file', 'contact_email', 'facebook_url', 'hero_bg_main_file'] as $name) {
            $page->assertSee('name="'.$name.'"', false);
        }

        $this->assertSame(1, substr_count($page->getContent(), 'class="card card-flush"'));
        $this->assertSame('president', SiteSettings::tabOf('president_message'));
        $this->assertSame('about', SiteSettings::tabOf('value_3_text'));
        $this->assertSame('home', SiteSettings::tabOf('pillar_2_title'));
    }

    public function test_visitor_can_subscribe_and_duplicates_get_the_same_answer(): void
    {
        $this->post('/fr/newsletter', ['email' => 'Sami@Example.test'])
            ->assertRedirect(route('site.about', ['locale' => 'fr']).'#newsletter')
            ->assertSessionHas('newsletter_sent');

        $this->post('/fr/newsletter', ['email' => 'sami@example.test'])->assertSessionHas('newsletter_sent');

        $this->assertDatabaseCount('newsletter_subscribers', 1);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'sami@example.test', 'locale' => 'fr']);
    }

    public function test_invalid_email_and_honeypot_are_rejected(): void
    {
        $this->post('/fr/newsletter', ['email' => 'pas-un-email'])->assertSessionHasErrors('email');
        $this->post('/fr/newsletter', ['email' => 'bot@example.test', 'website' => 'http://spam.test'])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_unsubscribe_requires_confirmation_and_resubscribing_reactivates(): void
    {
        $subscriber = NewsletterSubscriber::query()->create(['email' => 'a@example.test', 'locale' => 'fr', 'subscribed_at' => now()]);
        $token = $subscriber->unsubscribe_token;

        $this->get("/fr/newsletter/desinscription/{$token}")->assertOk();
        $this->assertNull($subscriber->fresh()->unsubscribed_at);

        $this->post("/fr/newsletter/desinscription/{$token}")->assertRedirect();
        $this->assertNotNull($subscriber->fresh()->unsubscribed_at);
        $this->get("/fr/newsletter/desinscription/{$token}")->assertOk()->assertSee(__('site.newsletter.unsubscribed'));

        $this->post('/fr/newsletter', ['email' => 'a@example.test']);
        $this->assertNull($subscriber->fresh()->unsubscribed_at);

        $this->get('/fr/newsletter/desinscription/'.str_repeat('a', 48))->assertNotFound();
    }

    public function test_admin_can_list_export_and_delete_subscribers(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $kept = NewsletterSubscriber::query()->create(['email' => 'ok@example.test', 'locale' => 'fr', 'subscribed_at' => now()]);
        NewsletterSubscriber::query()->create(['email' => '=cmd@example.test', 'locale' => 'en', 'subscribed_at' => now()]);
        NewsletterSubscriber::query()->create(['email' => 'gone@example.test', 'locale' => 'fr', 'subscribed_at' => now(), 'unsubscribed_at' => now()]);

        $this->actingAs($admin)->get('/admin/newsletter')->assertOk()->assertSee('ok@example.test')->assertDontSee('gone@example.test');

        $csv = $this->actingAs($admin)->get('/admin/newsletter/export');
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('ok@example.test', $content);
        $this->assertStringContainsString("'=cmd@example.test", $content);
        $this->assertStringNotContainsString('gone@example.test', $content);

        $this->actingAs($admin)->delete("/admin/newsletter/{$kept->id}")->assertRedirect();
        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $kept->id]);
    }

    public function test_scoped_admin_can_access_newsletter(): void
    {
        $this->get('/admin/newsletter')->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $this->grantAccess($admin, 'entity_admin', 'university');

        $this->actingAs($admin)->get('/admin/newsletter')->assertOk();
        $this->actingAs(User::factory()->create())->get('/admin/newsletter')->assertForbidden();
    }

    public function test_newsletter_module_is_in_the_catalog(): void
    {
        ModuleCatalog::sync();

        $this->assertDatabaseHas('app_modules', ['key' => 'website.newsletter']);
    }
}
