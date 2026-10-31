<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\StoreColor;
use App\Models\StoreDeliveryMethod;
use App\Models\StoreOrder;
use App\Models\StorePaymentMethod;
use App\Models\StoreProduct;
use App\Models\StoreProductImage;
use App\Models\StoreReview;
use App\Models\StoreSize;
use App\Models\StoreVariant;
use App\Models\User;
use App\Support\Store\StoreCart;
use App\Support\Store\StoreOrders;
use App\Support\Store\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

/** Fiche article : galerie, fiche textile, guide des tailles et avis des acheteurs. */
class StoreProductPageTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $this->grantPlatformAdmin($user);

        return $user->fresh();
    }

    /** @return array{0: StoreProduct, 1: StoreVariant} */
    private function product(): array
    {
        StoreSettings::save(['enabled' => true]);

        $product = StoreProduct::query()->create([
            'name' => 'Sweat Tunivisions', 'slug' => 'sweat-tunivisions', 'price' => 60, 'is_active' => true,
            'material' => 'Molleton gratté', 'composition' => '80 % coton, 20 % polyester', 'weight' => '280 g/m²',
            'care' => "Lavage à 30 °C\nNe pas repasser sur l'impression",
            'details' => "Premier paragraphe détaillé.\n\nSecond paragraphe détaillé.",
            'size_guide' => "Taille | Poitrine | Longueur\nS | 52 | 68\nM | 55 | 70",
        ]);
        $variant = StoreVariant::query()->create([
            'product_id' => $product->id,
            'color_id' => StoreColor::query()->create(['name' => 'Bordeaux', 'hex' => '#800020'])->id,
            'size_id' => StoreSize::query()->create(['name' => 'M'])->id,
            'stock' => 5,
        ]);

        return [$product, $variant];
    }

    private function deliveredOrder(User $user, StoreVariant $variant): StoreOrder
    {
        StoreDeliveryMethod::query()->delete();
        StorePaymentMethod::query()->delete();
        $delivery = StoreDeliveryMethod::query()->create(['name' => 'Livraison', 'kind' => 'home', 'fee' => 7, 'is_active' => true]);
        $payment = StorePaymentMethod::query()->create(['key' => 'cod', 'name' => 'À la livraison', 'applies_to' => 'all', 'is_active' => true]);

        StoreCart::addVariant($user, $variant, 1);
        $order = StoreOrders::place($user, [
            'delivery_method_id' => $delivery->id, 'payment_method_id' => $payment->id,
            'ship_name' => 'Sami', 'ship_phone' => '20 123 456',
            'governorate_id' => Governorate::query()->firstOrCreate(['slug' => 'tunis'], ['name' => 'Tunis'])->id,
            'city' => 'Tunis', 'address' => '1 rue de la Liberté',
        ]);
        $order->update(['status' => 'delivered']);

        return $order;
    }

    public function test_product_page_shows_gallery_textile_sheet_and_size_guide(): void
    {
        [$product] = $this->product();
        $product->update(['image_path' => 'store/products/main.jpg']);
        StoreProductImage::query()->create(['product_id' => $product->id, 'path' => 'store/products/side.jpg', 'sort_order' => 1]);

        $this->assertCount(2, $product->fresh()->gallery());

        $this->actingAs(User::factory()->create())->get(route('store.products.show', $product->slug))
            ->assertOk()
            ->assertSee('store/products/main.jpg', false)
            ->assertSee('store/products/side.jpg', false)
            ->assertSee('data-color', false)
            ->assertSee('data-size', false)
            ->assertSee('Bordeaux')
            ->assertSee('Molleton gratté')
            ->assertSee('80 % coton, 20 % polyester')
            ->assertSee('Lavage à 30 °C')
            ->assertSee('Second paragraphe détaillé.')
            ->assertSee('Guide des tailles')
            ->assertSee('Poitrine')
            ->assertSee('Aucun avis pour le moment');
    }

    public function test_size_guide_is_hidden_without_enough_rows(): void
    {
        [$product] = $this->product();
        $product->update(['size_guide' => 'Taille | Poitrine']);

        $this->assertNull($product->fresh()->sizeGuideTable());
        $this->actingAs(User::factory()->create())->get(route('store.products.show', $product->slug))->assertOk()->assertDontSee('id="guide-tailles"', false);
    }

    public function test_only_buyers_with_a_delivered_order_can_review(): void
    {
        [$product, $variant] = $this->product();
        $stranger = User::factory()->create();
        $buyer = User::factory()->create(['name' => 'Sami Trabelsi']);
        $order = $this->deliveredOrder($buyer, $variant);
        $payload = ['rating' => 5, 'title' => 'Très bien', 'body' => 'Qualité au top.'];

        $this->actingAs($stranger)->post(route('store.reviews.store', $product->slug), $payload)->assertSessionHasErrors('review');
        $this->assertSame(0, StoreReview::query()->count());

        $order->update(['status' => 'shipped']);
        $this->actingAs($buyer)->post(route('store.reviews.store', $product->slug), $payload)->assertSessionHasErrors('review');

        $order->update(['status' => 'delivered']);
        $this->actingAs($buyer)->post(route('store.reviews.store', $product->slug), $payload)->assertRedirect();
        $this->assertSame(1, StoreReview::query()->count());

        // Un seul avis par membre : la deuxième soumission le met à jour.
        $this->actingAs($buyer)->post(route('store.reviews.store', $product->slug), ['rating' => 4, 'body' => 'Après lavage, toujours bien.']);
        $this->assertSame(1, StoreReview::query()->count());
        $this->assertSame(4, StoreReview::query()->first()->rating);
    }

    public function test_review_is_validated(): void
    {
        [$product, $variant] = $this->product();
        $buyer = User::factory()->create();
        $this->deliveredOrder($buyer, $variant);

        $this->actingAs($buyer)->post(route('store.reviews.store', $product->slug), ['rating' => 9])->assertSessionHasErrors('rating');
        $this->actingAs($buyer)->post(route('store.reviews.store', $product->slug), [])->assertSessionHasErrors('rating');
    }

    public function test_reviews_are_listed_with_average_and_hidden_ones_are_excluded(): void
    {
        [$product, $variant] = $this->product();
        $first = User::factory()->create(['name' => 'Sami Trabelsi']);
        $second = User::factory()->create(['name' => 'Leila Ben Ali']);
        $third = User::factory()->create(['name' => 'Karim Hidden']);
        StoreReview::query()->create(['product_id' => $product->id, 'user_id' => $first->id, 'rating' => 5, 'title' => 'Parfait', 'body' => 'Excellent sweat.']);
        StoreReview::query()->create(['product_id' => $product->id, 'user_id' => $second->id, 'rating' => 4, 'body' => 'Bien mais grand.']);
        StoreReview::query()->create(['product_id' => $product->id, 'user_id' => $third->id, 'rating' => 1, 'body' => 'Avis masqué ici.', 'status' => 'hidden']);

        $summary = $product->fresh('reviews')->ratingSummary();
        $this->assertSame(2, $summary['count']);
        $this->assertSame(4.5, $summary['average']);

        $this->actingAs(User::factory()->create())->get(route('store.products.show', $product->slug))
            ->assertOk()
            ->assertSee('4,5')
            ->assertSee('(2 avis)')
            ->assertSee('Excellent sweat.')
            ->assertSee('Sami T.')
            ->assertDontSee('Avis masqué ici.')
            ->assertDontSee('Karim');
    }

    public function test_admin_moderates_reviews(): void
    {
        [$product] = $this->product();
        $review = StoreReview::query()->create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 3, 'body' => 'Moyen.']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.store.reviews.index'))->assertOk()->assertSee('Moyen.');

        $this->actingAs($admin)->put(route('admin.store.reviews.update', $review), ['status' => 'hidden'])->assertRedirect();
        $this->assertSame('hidden', $review->fresh()->status);

        $this->actingAs($admin)->get(route('admin.store.reviews.index', ['status' => 'hidden']))->assertOk()->assertSee('Republier');
        $this->actingAs($admin)->put(route('admin.store.reviews.update', $review), ['status' => 'bogus'])->assertSessionHasErrors('status');

        $this->actingAs($admin)->delete(route('admin.store.reviews.destroy', $review))->assertRedirect();
        $this->assertSame(0, StoreReview::query()->count());

        $this->actingAs(User::factory()->create())->get(route('admin.store.reviews.index'))->assertForbidden();
    }

    public function test_admin_manages_gallery_and_textile_sheet(): void
    {
        Storage::fake('public');
        [$product] = $this->product();
        $admin = $this->admin();
        $color = StoreColor::query()->first();

        $this->actingAs($admin)->post(route('admin.store.products.images.store', $product), [
            'photos' => [UploadedFile::fake()->image('a.jpg', 600, 600), UploadedFile::fake()->image('b.png', 600, 600)],
            'color_id' => $color->id,
        ])->assertRedirect();

        $images = $product->images()->get();
        $this->assertCount(2, $images);
        $this->assertSame($color->id, $images[0]->color_id);
        Storage::disk('public')->assertExists($images[0]->path);

        $this->actingAs($admin)->put(route('admin.store.products.images.update', $images[1]), ['color_id' => '', 'sort_order' => 0])->assertRedirect();
        $this->assertNull($images[1]->fresh()->color_id);

        $this->actingAs($admin)->post(route('admin.store.products.images.store', $product), ['photos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])->assertSessionHasErrors('photos.0');

        $this->actingAs($admin)->put(route('admin.store.products.update', $product), [
            'name' => 'Sweat Tunivisions', 'price' => 60, 'is_active' => 1,
            'material' => 'Coton bio', 'composition' => '100 % coton', 'weight' => '300 g/m²', 'fit' => 'Oversize',
            'care' => 'Lavage à 40 °C', 'details' => 'Détails.', 'size_guide' => "Taille | Poitrine\nS | 50",
        ])->assertRedirect();
        $this->assertSame('Coton bio', $product->fresh()->material);
        $this->assertSame('Oversize', $product->fresh()->fit);

        $this->actingAs($admin)->get(route('admin.store.products.edit', $product))->assertOk()->assertSee('Galerie photos')->assertSee('Guide des tailles');

        $path = $images[0]->path;
        $this->actingAs($admin)->delete(route('admin.store.products.images.destroy', $images[0]))->assertRedirect();
        Storage::disk('public')->assertMissing($path);
        $this->assertSame(1, $product->images()->count());

        $remaining = $product->images()->first()->path;
        $this->actingAs($admin)->delete(route('admin.store.products.destroy', $product))->assertRedirect();
        Storage::disk('public')->assertMissing($remaining);
        $this->assertSame(0, StoreProductImage::query()->count());
    }
}
