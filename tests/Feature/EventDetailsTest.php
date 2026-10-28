<?php

namespace Tests\Feature;

use App\Models\FoundationEvent;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class EventDetailsTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return $extra + [
            'title' => 'Forum des talents',
            'scope' => 'university',
            'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'approval_required' => '0',
            'status' => 'published',
        ];
    }

    public function test_admin_sets_governorate_cover_and_price_and_they_show_on_the_site(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        $sfax = Governorate::query()->create(['name' => 'Sfax', 'slug' => 'sfax']);

        $this->actingAs($admin)->post('/admin/evenements', $this->payload([
            'location' => 'Salle Ibn Khaldoun',
            'governorate_id' => $sfax->id,
            'price' => '15.5',
            'cover' => UploadedFile::fake()->image('cover.jpg', 800, 400),
        ]))->assertRedirect();

        $event = FoundationEvent::query()->firstOrFail();
        $this->assertSame($sfax->id, $event->governorate_id);
        $this->assertSame('15.50', $event->price);
        $this->assertStringStartsWith('events/covers/', $event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);

        $this->get('/fr/evenements/'.$event->id)->assertOk()
            ->assertSee('Salle Ibn Khaldoun')->assertSee('Gouvernorat')->assertSee('Sfax')
            ->assertSee('15,5 TND')->assertSee($event->cover_image_path, false);

        $this->get('/fr/evenements')->assertOk()->assertSee('Sfax')->assertSee('15,5 TND');
    }

    public function test_zero_price_is_free_and_empty_price_is_hidden(): void
    {
        $free = FoundationEvent::query()->create(['title' => 'Atelier libre', 'starts_at' => now()->addDay(), 'status' => 'published', 'price' => 0]);
        $unknown = FoundationEvent::query()->create(['title' => 'Atelier sans prix', 'starts_at' => now()->addDay(), 'status' => 'published']);

        $this->assertSame('Gratuit', $free->priceLabel());
        $this->assertNull($unknown->priceLabel());
        $this->get('/fr/evenements/'.$free->id)->assertOk()->assertSee('Gratuit');
        $this->get('/fr/evenements/'.$unknown->id)->assertOk()->assertDontSee('Prix de participation');
    }

    public function test_cover_can_be_replaced_and_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->post('/admin/evenements', $this->payload(['cover' => UploadedFile::fake()->image('a.jpg')]))->assertRedirect();
        $event = FoundationEvent::query()->firstOrFail();
        $first = $event->cover_image_path;

        $this->actingAs($admin)->put("/admin/evenements/{$event->id}", $this->payload(['cover' => UploadedFile::fake()->image('b.png')]))->assertRedirect();
        $second = $event->fresh()->cover_image_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($admin)->put("/admin/evenements/{$event->id}", $this->payload(['cover_remove' => '1']))->assertRedirect();
        $this->assertNull($event->fresh()->cover_image_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_invalid_cover_price_and_governorate_are_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');

        $this->actingAs($admin)->post('/admin/evenements', $this->payload([
            'governorate_id' => 9999,
            'price' => '-5',
            'cover' => UploadedFile::fake()->create('script.php', 5, 'text/php'),
        ]))->assertSessionHasErrors(['governorate_id', 'price', 'cover']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_the_edit_form_offers_the_new_fields(): void
    {
        $admin = User::factory()->create();
        $this->grantAccess($admin, 'global_admin');
        Governorate::query()->create(['name' => 'Sousse', 'slug' => 'sousse']);
        $event = FoundationEvent::query()->create(['title' => 'Test', 'starts_at' => now()->addDay(), 'status' => 'draft']);

        $this->actingAs($admin)->get("/admin/evenements/{$event->id}/modifier")->assertOk()
            ->assertSee('Gouvernorat')->assertSee('Sousse')->assertSee('Photo de couverture')->assertSee('Prix de participation')
            ->assertSee('multipart/form-data', false);
    }
}
