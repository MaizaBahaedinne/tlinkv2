<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InnerPagesRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_inner_pages_share_the_immersive_header_in_every_locale(): void
    {
        foreach (['fr', 'en', 'ar'] as $locale) {
            foreach (['a-propos', 'projets', 'evenements', 'contact', 'actualites'] as $page) {
                $this->get("/{$locale}/{$page}")
                    ->assertOk()
                    ->assertSee('page-hero', false)
                    ->assertSee('hero-title', false);
            }
        }
    }

    public function test_page_kickers_are_translated(): void
    {
        $this->get('/fr/contact')->assertSee('Parlons-nous');
        $this->get('/en/contact')->assertSee('Let&#039;s talk', false);
        $this->get('/fr/evenements')->assertSee('Agenda');
        $this->get('/fr/projets')->assertSee('Nos réalisations');
        $this->get('/fr/a-propos')->assertSee('La Fondation');
    }

    public function test_contact_page_keeps_its_form_fields(): void
    {
        $page = $this->get('/fr/contact')->assertOk();

        foreach (['f-name', 'f-email', 'f-subject', 'f-message'] as $id) {
            $page->assertSee('id="'.$id.'"', false);
        }

        $page->assertSee('site-input', false);
    }
}
