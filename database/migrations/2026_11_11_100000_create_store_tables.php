<?php

use App\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Référentiels du catalogue : catégories, types d'article, couleurs et tailles réutilisables.
        Schema::create('store_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('store_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_colors', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60);
            $table->string('hex', 7)->default('#CCCCCC');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_sizes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 30);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('store_categories')->nullOnDelete();
            $table->foreignId('type_id')->nullable()->constrained('store_types')->nullOnDelete();
            $table->string('name', 160);
            $table->string('slug', 190)->unique();
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'category_id']);
        });

        // Une variante = une combinaison couleur/taille, avec son propre stock (et éventuellement son prix).
        Schema::create('store_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('store_colors')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->constrained('store_sizes')->nullOnDelete();
            $table->string('sku', 60)->nullable()->unique();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('product_id');
        });

        // Journal de stock : chaque entrée, sortie ou correction y laisse une trace.
        Schema::create('store_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('variant_id')->constrained('store_variants')->cascadeOnDelete();
            $table->integer('delta');
            $table->unsignedInteger('balance');
            $table->string('reason', 20);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('return_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['variant_id', 'id']);
        });

        // Packs : plusieurs articles vendus ensemble à un prix fixe.
        Schema::create('store_packs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 190)->unique();
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('store_pack_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pack_id')->constrained('store_packs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('store_products')->cascadeOnDelete();
            // Variante imposée ; vide = l'acheteur choisit la couleur et la taille.
            $table->foreignId('variant_id')->nullable()->constrained('store_variants')->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('store_cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('store_variants')->cascadeOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained('store_packs')->cascadeOnDelete();
            // Pack : variante choisie pour chaque article (id de ligne du pack => id de variante).
            $table->json('selection')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();

            $table->index('user_id');
        });

        // Modes de livraison et de paiement : entièrement paramétrables par l'administration.
        Schema::create('store_delivery_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('kind', 8)->default('home');
            $table->decimal('fee', 8, 2)->default(0);
            $table->decimal('free_above', 10, 2)->nullable();
            // Tarif par gouvernorat (id => frais) : remplace le tarif de base pour ce gouvernorat.
            $table->json('governorate_fees')->nullable();
            $table->string('estimated_delay', 100)->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 100);
            $table->text('instructions')->nullable();
            // Livraison à laquelle le mode s'applique : all, home (à domicile) ou pickup (retrait).
            $table->string('applies_to', 8)->default('all');
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('payment_status', 20)->default('unpaid');
            $table->foreignId('delivery_method_id')->nullable()->constrained('store_delivery_methods')->nullOnDelete();
            $table->string('delivery_name', 100);
            $table->string('delivery_kind', 8)->default('home');
            $table->foreignId('payment_method_id')->nullable()->constrained('store_payment_methods')->nullOnDelete();
            $table->string('payment_key', 32);
            $table->string('payment_name', 100);
            $table->string('ship_name', 120);
            $table->string('ship_phone', 30);
            $table->foreignId('ship_governorate_id')->nullable()->constrained('governorates')->nullOnDelete();
            $table->string('ship_city', 100)->nullable();
            $table->string('ship_address', 255)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });

        // Lignes figées à la commande. Un pack = une ligne « pack » (au prix du pack) + ses lignes « pack_item » (prix 0, pour le stock).
        Schema::create('store_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('store_order_items')->cascadeOnDelete();
            $table->string('kind', 10)->default('product');
            $table->foreignId('variant_id')->nullable()->constrained('store_variants')->nullOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained('store_packs')->nullOnDelete();
            $table->string('name', 190);
            $table->string('variant_label', 120)->nullable();
            $table->string('sku', 60)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->unsignedSmallInteger('returned_quantity')->default(0);
            $table->timestamps();

            $table->index('order_id');
        });

        Schema::create('store_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('order_id')->constrained('store_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 12)->default('requested');
            $table->string('reason', 255);
            $table->text('details')->nullable();
            $table->string('resolution', 10)->default('refund');
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->boolean('restocked')->default(false);
            $table->text('admin_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('store_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_id')->constrained('store_returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('store_order_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });

        // Modes de paiement proposés par défaut : tous désactivés, l'administration choisit ceux à ouvrir.
        $now = now();

        DB::table('store_payment_methods')->insert([
            ['key' => 'cod', 'name' => 'Paiement à la livraison', 'instructions' => 'Réglez en espèces au livreur à la réception de votre commande.', 'applies_to' => 'home', 'is_active' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'cash_pickup', 'name' => 'Paiement au retrait', 'instructions' => 'Réglez sur place lorsque vous retirez votre commande.', 'applies_to' => 'pickup', 'is_active' => false, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'bank_transfer', 'name' => 'Virement bancaire', 'instructions' => "Effectuez un virement en indiquant la référence de votre commande. Votre commande est préparée dès réception du paiement.", 'applies_to' => 'all', 'is_active' => false, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'cash', 'name' => 'Espèces auprès de l’équipe', 'instructions' => 'Réglez en espèces auprès de l’équipe Tunivisions en indiquant la référence de votre commande.', 'applies_to' => 'all', 'is_active' => false, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('store_delivery_methods')->insert([
            ['name' => 'Livraison à domicile', 'kind' => 'home', 'fee' => 0, 'is_active' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Retrait sur place', 'kind' => 'pickup', 'fee' => 0, 'is_active' => false, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);

        ModuleCatalog::sync();
    }

    public function down(): void
    {
        foreach (['store_return_items', 'store_returns', 'store_order_items', 'store_orders', 'store_payment_methods', 'store_delivery_methods', 'store_cart_items', 'store_pack_items', 'store_packs', 'store_stock_movements', 'store_variants', 'store_products', 'store_sizes', 'store_colors', 'store_types', 'store_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
