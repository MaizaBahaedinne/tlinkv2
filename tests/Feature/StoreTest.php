<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\StoreCartItem;
use App\Models\StoreColor;
use App\Models\StoreDeliveryMethod;
use App\Models\StoreOrder;
use App\Models\StorePack;
use App\Models\StorePaymentMethod;
use App\Models\StoreProduct;
use App\Models\StoreSize;
use App\Models\StoreVariant;
use App\Models\User;
use App\Support\Store\StoreCart;
use App\Support\Store\StoreOrders;
use App\Support\Store\StoreReturns;
use App\Support\Store\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\GrantsAccess;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use GrantsAccess;
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $this->grantPlatformAdmin($user);

        return $user->fresh();
    }

    private function governorate(string $name = 'Tunis'): Governorate
    {
        return Governorate::query()->firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], ['name' => $name]);
    }

    private function open(): void
    {
        StoreSettings::save(['enabled' => true]);
        StoreDeliveryMethod::query()->update(['is_active' => true]);
        StorePaymentMethod::query()->update(['is_active' => true]);
    }

    /** @return array{0: StoreProduct, 1: StoreVariant} */
    private function product(int $stock = 10, float $price = 20.0): array
    {
        $product = StoreProduct::query()->create(['name' => 'T-shirt', 'slug' => 't-shirt', 'price' => $price, 'is_active' => true]);
        $variant = StoreVariant::query()->create([
            'product_id' => $product->id,
            'color_id' => StoreColor::query()->create(['name' => 'Rouge', 'hex' => '#ff0000'])->id,
            'size_id' => StoreSize::query()->create(['name' => 'M'])->id,
            'stock' => $stock,
            'low_stock_threshold' => 2,
        ]);

        return [$product, $variant];
    }

    private function delivery(string $kind = 'home', float $fee = 7.0, ?float $freeAbove = null, ?array $perGovernorate = null): StoreDeliveryMethod
    {
        StoreDeliveryMethod::query()->delete();

        return StoreDeliveryMethod::query()->create([
            'name' => 'Livraison test', 'kind' => $kind, 'fee' => $fee, 'free_above' => $freeAbove,
            'governorate_fees' => $perGovernorate, 'is_active' => true,
        ]);
    }

    private function payment(string $appliesTo = 'all'): StorePaymentMethod
    {
        StorePaymentMethod::query()->delete();

        return StorePaymentMethod::query()->create(['key' => 'cod', 'name' => 'À la livraison', 'applies_to' => $appliesTo, 'is_active' => true]);
    }

    private function checkoutData(StoreDeliveryMethod $delivery, StorePaymentMethod $payment, array $extra = []): array
    {
        return $extra + [
            'delivery_method_id' => $delivery->id,
            'payment_method_id' => $payment->id,
            'ship_name' => 'Sami Trabelsi',
            'ship_phone' => '20 123 456',
            'governorate_id' => $this->governorate()->id,
            'city' => 'Tunis',
            'address' => '1 rue de la Liberté',
        ];
    }

    private function place(User $user, StoreVariant $variant, int $quantity = 2, array $extra = []): StoreOrder
    {
        StoreCart::addVariant($user, $variant, $quantity);

        return StoreOrders::place($user, $this->checkoutData(StoreDeliveryMethod::query()->first(), StorePaymentMethod::query()->first(), $extra));
    }

    public function test_store_is_closed_until_enabled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/boutique')->assertNotFound();

        StoreSettings::save(['enabled' => true]);

        $this->actingAs($user)->get('/boutique')->assertOk();
    }

    public function test_store_requires_login(): void
    {
        StoreSettings::save(['enabled' => true]);

        $this->get('/boutique')->assertRedirect();
    }

    public function test_catalog_and_product_pages_render(): void
    {
        $this->open();
        [$product] = $this->product();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/boutique')->assertOk()->assertSee('T-shirt');
        $this->actingAs($user)->get('/boutique?q=zzz')->assertOk()->assertDontSee('T-shirt');
        $this->actingAs($user)->get(route('store.products.show', $product->slug))->assertOk()->assertSee('Rouge')->assertSee('data-size', false);
        $this->actingAs($user)->get(route('store.cart.index'))->assertOk();
    }

    public function test_inactive_product_is_not_visible(): void
    {
        $this->open();
        [$product] = $this->product();
        $product->update(['is_active' => false]);

        $this->actingAs(User::factory()->create())->get(route('store.products.show', $product->slug))->assertNotFound();
    }

    public function test_cart_refuses_more_than_stock(): void
    {
        $this->open();
        [, $variant] = $this->product(3);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('store.cart.add'), ['variant_id' => $variant->id, 'quantity' => 4])->assertSessionHasErrors('quantity');
        $this->actingAs($user)->post(route('store.cart.add'), ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect(route('store.cart.index'));

        $this->assertSame(2, StoreCart::count($user));

        $this->actingAs($user)->post(route('store.cart.add'), ['variant_id' => $variant->id, 'quantity' => 2])->assertSessionHasErrors('quantity');
    }

    public function test_cart_items_belong_to_their_owner(): void
    {
        $this->open();
        [, $variant] = $this->product();
        $owner = User::factory()->create();
        StoreCart::addVariant($owner, $variant, 1);
        $item = StoreCartItem::query()->first();

        $this->actingAs(User::factory()->create())->delete(route('store.cart.remove', $item))->assertNotFound();
        $this->actingAs($owner)->patch(route('store.cart.update', $item), ['quantity' => 3])->assertSessionHasNoErrors();
        $this->assertSame(3, $item->fresh()->quantity);
        $this->actingAs($owner)->patch(route('store.cart.update', $item), ['quantity' => 0]);
        $this->assertSame(0, StoreCartItem::query()->count());
    }

    public function test_placing_an_order_decrements_stock_and_empties_cart(): void
    {
        $this->open();
        [, $variant] = $this->product(10, 20);
        $this->delivery('home', 7);
        $this->payment();
        $user = User::factory()->create();

        StoreCart::addVariant($user, $variant, 3);

        $this->actingAs($user)->get(route('store.checkout'))->assertOk();
        $response = $this->actingAs($user)->post(route('store.checkout.place'), $this->checkoutData(StoreDeliveryMethod::query()->first(), StorePaymentMethod::query()->first()));

        $order = StoreOrder::query()->firstOrFail();
        $response->assertRedirect(route('store.orders.show', $order));

        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertSame(0, StoreCart::count($user));
        $this->assertSame('67.00', $order->total);
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertDatabaseHas('store_stock_movements', ['variant_id' => $variant->id, 'delta' => -3, 'balance' => 7, 'reason' => 'sale']);

        $this->actingAs($user)->get(route('store.orders.show', $order))->assertOk()->assertSee($order->reference);
        $this->actingAs($user)->get(route('store.orders.index'))->assertOk()->assertSee($order->reference);
    }

    public function test_order_fails_when_stock_ran_out_after_adding_to_cart(): void
    {
        $this->open();
        [, $variant] = $this->product(5);
        $this->delivery();
        $this->payment();
        $user = User::factory()->create();
        StoreCart::addVariant($user, $variant, 4);
        $variant->update(['stock' => 1]);

        try {
            StoreOrders::place($user, $this->checkoutData(StoreDeliveryMethod::query()->first(), StorePaymentMethod::query()->first()));
            $this->fail('La commande aurait dû être refusée.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cart', $exception->errors());
        }

        $this->assertSame(1, $variant->fresh()->stock);
        $this->assertSame(0, StoreOrder::query()->count());
    }

    public function test_delivery_fee_rules(): void
    {
        $governorate = $this->governorate('Tunis');
        $other = $this->governorate('Sfax');

        $delivery = $this->delivery('home', 8, 100, [(string) $governorate->id => 5]);

        $this->assertSame(5.0, $delivery->feeFor(50, $governorate->id));
        $this->assertSame(8.0, $delivery->feeFor(50, $other->id));
        $this->assertSame(0.0, $delivery->feeFor(100, $governorate->id));
        $this->assertSame(0.0, $this->delivery('pickup', 9)->feeFor(10, null));
    }

    public function test_pickup_needs_no_address_but_home_delivery_does(): void
    {
        $this->open();
        [, $variant] = $this->product();
        $payment = $this->payment();
        $user = User::factory()->create();

        $home = $this->delivery('home');
        StoreCart::addVariant($user, $variant, 1);

        try {
            StoreOrders::place($user, ['delivery_method_id' => $home->id, 'payment_method_id' => $payment->id, 'ship_name' => 'A', 'ship_phone' => '2012345']);
            $this->fail('Adresse obligatoire.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('address', $exception->errors());
        }

        $pickup = $this->delivery('pickup');
        $order = StoreOrders::place($user, ['delivery_method_id' => $pickup->id, 'payment_method_id' => $payment->id, 'ship_name' => 'A', 'ship_phone' => '2012345']);

        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertNull($order->ship_address);
        $this->assertSame('Prête au retrait', $order->fresh()->forceFill(['status' => 'shipped'])->statusLabel());
    }

    public function test_payment_method_must_fit_delivery_kind_and_be_active(): void
    {
        $this->open();
        [, $variant] = $this->product();
        $delivery = $this->delivery('home');
        $payment = $this->payment('pickup');
        $user = User::factory()->create();
        StoreCart::addVariant($user, $variant, 1);

        $this->expectException(ValidationException::class);
        StoreOrders::place($user, $this->checkoutData($delivery, $payment));
    }

    public function test_inactive_methods_cannot_be_used(): void
    {
        $this->open();
        [, $variant] = $this->product();
        $delivery = $this->delivery('home');
        $payment = $this->payment();
        $payment->update(['is_active' => false]);
        $user = User::factory()->create();
        StoreCart::addVariant($user, $variant, 1);

        $this->expectException(ValidationException::class);
        StoreOrders::place($user, $this->checkoutData($delivery, $payment));
    }

    public function test_member_cancels_pending_order_and_stock_comes_back(): void
    {
        $this->open();
        [, $variant] = $this->product(10);
        $this->delivery();
        $this->payment();
        $user = User::factory()->create();
        $order = $this->place($user, $variant, 4);

        $this->assertSame(6, $variant->fresh()->stock);

        $this->actingAs($user)->post(route('store.orders.cancel', $order))->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $variant->fresh()->stock);

        // Une seconde annulation ne remet pas le stock une deuxième fois.
        $this->actingAs($user)->post(route('store.orders.cancel', $order))->assertSessionHasErrors('order');
        $this->assertSame(10, $variant->fresh()->stock);
    }

    public function test_member_cannot_cancel_once_confirmed(): void
    {
        $this->open();
        [, $variant] = $this->product(10);
        $this->delivery();
        $this->payment();
        $user = User::factory()->create();
        $order = $this->place($user, $variant, 1);
        StoreOrders::advance($order, 'confirmed');

        $this->actingAs($user)->post(route('store.orders.cancel', $order))->assertSessionHasErrors('order');
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_orders_of_other_members_are_hidden(): void
    {
        $this->open();
        [, $variant] = $this->product();
        $this->delivery();
        $this->payment();
        $order = $this->place(User::factory()->create(), $variant, 1);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('store.orders.show', $order))->assertNotFound();
        $this->actingAs($stranger)->post(route('store.orders.cancel', $order))->assertNotFound();
        $this->actingAs($stranger)->post(route('store.orders.return', $order), [])->assertNotFound();
    }

    public function test_order_status_only_moves_forward(): void
    {
        $this->open();
        [, $variant] = $this->product();
        $this->delivery();
        $this->payment();
        $order = $this->place(User::factory()->create(), $variant, 1);

        $this->assertFalse(StoreOrders::advance($order, 'pending'));
        $this->assertTrue(StoreOrders::advance($order, 'shipped'));
        $this->assertFalse(StoreOrders::advance($order->fresh(), 'preparing'));
        $this->assertTrue(StoreOrders::advance($order->fresh(), 'delivered'));
        $this->assertNotNull($order->fresh()->delivered_at);
        $this->assertFalse(StoreOrders::cancel($order->fresh()));
    }

    public function test_pack_order_reserves_component_stock(): void
    {
        $this->open();
        [$product, $variant] = $this->product(10, 20);
        $pack = StorePack::query()->create(['name' => 'Pack rentrée', 'slug' => 'pack-rentree', 'price' => 30, 'is_active' => true]);
        $item = $pack->items()->create(['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2]);
        $this->delivery('pickup');
        $this->payment();
        $user = User::factory()->create();

        $this->assertSame(5, $pack->load('items.product.variants', 'items.variant')->availableUnits());

        $this->actingAs($user)->post(route('store.cart.add-pack', $pack), ['quantity' => 1])->assertSessionHasErrors('choices');
        $this->actingAs($user)->post(route('store.cart.add-pack', $pack), ['quantity' => 2, 'choices' => [$item->id => $variant->id]])->assertRedirect(route('store.cart.index'));

        $order = StoreOrders::place($user, $this->checkoutData(StoreDeliveryMethod::query()->first(), StorePaymentMethod::query()->first()));

        $this->assertSame('60.00', $order->subtotal);
        $this->assertSame(6, $variant->fresh()->stock);
        $this->assertSame(1, $order->items()->where('kind', 'pack')->count());
        $this->assertSame(4, (int) $order->items()->where('kind', 'pack_item')->value('quantity'));
        $this->assertSame('0.00', $order->items()->where('kind', 'pack_item')->value('line_total'));

        $this->actingAs($user)->get(route('store.cart.index'))->assertOk();
        $this->actingAs($user)->get(route('store.packs.show', $pack->slug))->assertOk()->assertSee('Pack rentrée');
        $this->actingAs($user)->get(route('store.orders.show', $order))->assertOk()->assertSee('Pack rentrée');
    }

    public function test_marking_cash_order_paid_and_delivered(): void
    {
        $this->open();
        [, $variant] = $this->product();
        $this->delivery();
        $this->payment();
        $order = $this->place(User::factory()->create(), $variant, 1);

        $this->assertTrue(StoreOrders::markPaid($order));
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertFalse(StoreOrders::markPaid($order->fresh()));
    }

    public function test_return_flow_refunds_and_restocks(): void
    {
        $this->open();
        [, $variant] = $this->product(10, 20);
        $this->delivery('home', 7);
        $this->payment();
        $user = User::factory()->create();
        $order = $this->place($user, $variant, 3);
        $item = $order->items()->first();

        $this->assertFalse(StoreReturns::canRequest($order->fresh()));

        StoreOrders::markPaid($order);
        StoreOrders::advance($order->fresh(), 'delivered');
        $order = $order->fresh();

        $this->assertTrue(StoreReturns::canRequest($order));
        $this->assertSame(3, StoreReturns::returnable($order)[0]['remaining']);

        $this->actingAs($user)->post(route('store.orders.return', $order), [
            'quantities' => [$item->id => 5], 'reason' => 'Trop petit', 'resolution' => 'refund',
        ])->assertSessionHasErrors('items');

        $this->actingAs($user)->post(route('store.orders.return', $order), [
            'quantities' => [$item->id => 2], 'reason' => 'Trop petit', 'resolution' => 'refund',
        ])->assertSessionHasNoErrors();

        $return = $order->returns()->firstOrFail();
        $this->assertSame('requested', $return->status);
        $this->assertSame(1, StoreReturns::returnable($order->fresh())[0]['remaining']);

        $this->assertTrue(StoreReturns::approve($return, 'OK'));
        $this->assertTrue(StoreReturns::receive($return->fresh(), true, null));
        $this->assertSame(9, $variant->fresh()->stock);
        $this->assertSame(2, $item->fresh()->returned_quantity);

        $this->assertTrue(StoreReturns::complete($return->fresh(), null, 'Remboursé'));
        $this->assertSame('completed', $return->fresh()->status);
        $this->assertSame('40.00', $return->fresh()->refund_amount);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);

        $this->actingAs($user)->get(route('store.orders.show', $order))->assertOk()->assertSee($return->reference);
    }

    public function test_rejected_return_frees_the_quantities(): void
    {
        $this->open();
        [, $variant] = $this->product();
        $this->delivery();
        $this->payment();
        $user = User::factory()->create();
        $order = $this->place($user, $variant, 2);
        StoreOrders::advance($order, 'delivered');
        $order = $order->fresh();
        $item = $order->items()->first();

        $return = StoreReturns::request($user, $order, [$item->id => 2], 'Défaut', null, 'exchange');

        $this->assertSame(0, StoreReturns::returnable($order->fresh())[0]['remaining'] ?? 0);
        $this->assertTrue(StoreReturns::reject($return, 'Hors garantie'));
        $this->assertSame(2, StoreReturns::returnable($order->fresh())[0]['remaining']);
    }

    public function test_return_window_is_enforced(): void
    {
        $this->open();
        StoreSettings::save(['return_days' => '5']);
        [, $variant] = $this->product();
        $this->delivery();
        $this->payment();
        $order = $this->place(User::factory()->create(), $variant, 1);
        StoreOrders::advance($order, 'delivered');
        $order->fresh()->forceFill(['delivered_at' => now()->subDays(6)])->save();

        $this->assertFalse(StoreReturns::canRequest($order->fresh()));
    }

    public function test_members_cannot_open_the_admin_store(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.store.products.index'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.store.orders.index'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.store.settings.edit'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.store.index'))->assertForbidden();
    }

    public function test_admin_overview_shows_checklist_then_figures(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/boutique')
            ->assertOk()
            ->assertSee('Mise en route')
            ->assertSee('Boutique fermée')
            ->assertSee('Aucune commande');

        $this->open();
        [, $variant] = $this->product(3);
        $this->delivery();
        $this->payment();
        $order = $this->place(User::factory()->create(), $variant, 2);

        $this->actingAs($admin)->get(route('admin.store.index'))
            ->assertOk()
            ->assertDontSee('Mise en route')
            ->assertSee($order->reference)
            ->assertSee('Stock à surveiller');
    }

    public function test_order_detail_shows_tracker_and_cancelled_notice(): void
    {
        $admin = $this->admin();
        $this->open();
        [, $variant] = $this->product();
        $this->delivery('pickup');
        $this->payment();
        $order = $this->place(User::factory()->create(), $variant, 1);
        StoreOrders::advance($order, 'shipped');

        $this->actingAs($admin)->get(route('admin.store.orders.show', $order))->assertOk()->assertSee('Prête au retrait')->assertSee('is-current', false);

        $order->fresh()->forceFill(['status' => 'pending'])->save();
        StoreOrders::cancel($order->fresh());

        $this->actingAs($admin)->get(route('admin.store.orders.show', $order))->assertOk()->assertSee('Commande annulée');
    }

    public function test_admin_pages_render(): void
    {
        $admin = $this->admin();
        $this->open();
        [$product, $variant] = $this->product();
        $this->delivery();
        $this->payment();
        $order = $this->place(User::factory()->create(), $variant, 1);
        StoreOrders::advance($order, 'delivered');
        $item = $order->items()->first();
        $return = StoreReturns::request($order->user, $order->fresh(), [$item->id => 1], 'Défaut', null, 'refund');
        StorePack::query()->create(['name' => 'Pack', 'slug' => 'pack', 'price' => 10, 'is_active' => true]);

        foreach ([
            route('admin.store.index'), route('admin.store.products.index'), route('admin.store.products.create'), route('admin.store.products.edit', $product),
            route('admin.store.packs.index'), route('admin.store.packs.create'), route('admin.store.refs.index'),
            route('admin.store.stock.index'), route('admin.store.orders.index'), route('admin.store.orders.show', $order),
            route('admin.store.returns.index'), route('admin.store.returns.show', $return), route('admin.store.settings.edit'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_creates_product_variants_and_adjusts_stock(): void
    {
        $admin = $this->admin();
        $color = StoreColor::query()->create(['name' => 'Bleu', 'hex' => '#0000ff']);
        $sizes = [StoreSize::query()->create(['name' => 'S']), StoreSize::query()->create(['name' => 'L'])];

        $this->actingAs($admin)->post(route('admin.store.products.store'), ['name' => 'Polo', 'price' => 35, 'is_active' => 1])->assertSessionHasNoErrors();
        $product = StoreProduct::query()->where('name', 'Polo')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.store.products.variants.store', $product), [
            'colors' => [$color->id], 'sizes' => [$sizes[0]->id, $sizes[1]->id], 'stock' => 8,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(16, $product->variants()->sum('stock'));

        $this->actingAs($admin)->post(route('admin.store.products.variants.store', $product), ['colors' => [$color->id], 'sizes' => [$sizes[0]->id]])->assertSessionHas('error');
        $this->assertSame(2, $product->variants()->count());

        $variant = $product->variants()->first();
        $this->actingAs($admin)->post(route('admin.store.stock.adjust', $variant), ['delta' => -3, 'reason' => 'adjustment'])->assertSessionHasNoErrors();
        $this->assertSame(5, $variant->fresh()->stock);

        $this->actingAs($admin)->post(route('admin.store.stock.adjust', $variant), ['delta' => -50, 'reason' => 'adjustment'])->assertSessionHasErrors();
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_admin_cannot_delete_ordered_variant_or_product(): void
    {
        $admin = $this->admin();
        $this->open();
        [$product, $variant] = $this->product();
        $this->delivery();
        $this->payment();
        $this->place(User::factory()->create(), $variant, 1);

        $this->actingAs($admin)->delete(route('admin.store.products.variants.destroy', $variant))->assertSessionHasErrors('variant');
        $this->actingAs($admin)->delete(route('admin.store.products.destroy', $product))->assertSessionHasErrors('product');
        $this->assertNotNull($variant->fresh());
    }

    public function test_admin_updates_settings_and_delivery_methods(): void
    {
        $admin = $this->admin();
        $governorate = $this->governorate();

        $this->actingAs($admin)->put(route('admin.store.settings.update'), ['enabled' => 1, 'name' => 'Tunivisions Stores', 'return_days' => 10])->assertSessionHasNoErrors();
        $this->assertTrue(StoreSettings::enabled());
        $this->assertSame(10, StoreSettings::returnDays());

        $this->actingAs($admin)->post(route('admin.store.settings.delivery.store'), [
            'name' => 'Aramex', 'kind' => 'home', 'fee' => 8, 'free_above' => 150, 'is_active' => 1,
            'governorate_fees' => [$governorate->id => 5, 999 => ''],
        ])->assertSessionHasNoErrors();

        $method = StoreDeliveryMethod::query()->where('name', 'Aramex')->firstOrFail();
        $this->assertSame([(string) $governorate->id => '5'], array_map('strval', $method->governorate_fees));

        $payment = StorePaymentMethod::query()->where('key', 'cod')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.store.settings.payment.update', $payment), ['name' => 'Cash', 'applies_to' => 'home', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($payment->fresh()->is_active);

        $this->actingAs($admin)->delete(route('admin.store.settings.delivery.destroy', $method))->assertSessionHasNoErrors();
        $this->assertNull($method->fresh());
    }

    public function test_admin_manages_order_from_the_backoffice(): void
    {
        $admin = $this->admin();
        $this->open();
        [, $variant] = $this->product();
        $this->delivery();
        $this->payment();
        $order = $this->place(User::factory()->create(), $variant, 1);

        $this->actingAs($admin)->post(route('admin.store.orders.status', $order), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $order->fresh()->status);

        $this->actingAs($admin)->post(route('admin.store.orders.pay', $order))->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->fresh()->payment_status);

        $this->actingAs($admin)->put(route('admin.store.orders.tracking', $order), ['tracking_number' => 'AB123'])->assertSessionHasNoErrors();
        $this->assertSame('AB123', $order->fresh()->tracking_number);

        $this->actingAs($admin)->post(route('admin.store.orders.cancel', $order))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $variant->fresh()->stock);
    }
}
