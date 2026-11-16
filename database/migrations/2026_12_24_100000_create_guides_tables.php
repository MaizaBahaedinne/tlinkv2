<?php

use App\Models\OrgNode;
use App\Support\Guides\StarterGuides;
use App\Support\ModuleCatalog;
use App\Support\ModulePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bibliothèque des guides d'utilisation : un guide = des étapes illustrées (capture, GIF ou icône), un repère animé
     * sur l'image, une notice (astuce, attention, information) et une durée. La progression de chaque membre est suivie.
     */
    public function up(): void
    {
        if (! Schema::hasTable('guides')) {
            Schema::create('guides', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('title', 160);
                $table->text('summary')->nullable();
                $table->string('category', 60)->nullable()->index();
                $table->string('audience', 20)->default('all'); // all | holders (membres qui occupent un poste)
                $table->string('icon', 40)->default('book-open');
                $table->string('accent', 20)->default('rose');
                $table->string('status', 20)->default('draft'); // draft | published
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['status', 'sort_order']);
            });
        }

        if (! Schema::hasTable('guide_steps')) {
            Schema::create('guide_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
                $table->unsignedSmallInteger('position')->default(0);
                $table->string('title', 160);
                $table->text('body')->nullable();
                $table->string('notice_type', 20)->nullable(); // info | tip | warning
                $table->text('notice')->nullable();
                $table->string('icon', 40)->nullable();
                $table->string('image_path')->nullable();
                $table->unsignedTinyInteger('hotspot_x')->nullable(); // repère animé : pourcentage de la largeur de l'image
                $table->unsignedTinyInteger('hotspot_y')->nullable(); // pourcentage de la hauteur
                $table->unsignedTinyInteger('duration')->default(7); // secondes en lecture automatique
                $table->timestamps();
                $table->index(['guide_id', 'position']);
            });
        }

        if (! Schema::hasTable('guide_progress')) {
            Schema::create('guide_progress', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedSmallInteger('last_step')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['guide_id', 'user_id']);
            });
        }

        ModuleCatalog::sync();

        // Les postes de portée globale qui gèrent déjà l'organisation reçoivent le nouveau module (sinon 403).
        OrgNode::query()
            ->where('kind', 'position')
            ->whereNull('scope')
            ->whereNotNull('module_permissions')
            ->each(function (OrgNode $node): void {
                $matrix = (array) $node->module_permissions;

                if (isset($matrix['help.guides']) || ! in_array('entity.manage', ModulePermissions::derivePermissions($matrix), true)) {
                    return;
                }

                $matrix['help.guides'] = ['read', 'create', 'update', 'delete'];
                $node->update(['module_permissions' => $matrix]);
            });

        StarterGuides::install();
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_progress');
        Schema::dropIfExists('guide_steps');
        Schema::dropIfExists('guides');
    }
};
