<?php

use App\Models\ClubTemplateSlot;
use App\Models\Setting;
use App\Support\OrgChart\ClubTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèle d'équipe de chaque type de club (University, High School, We Are Carthage) : postes, départements,
 * capacités et niveaux d'accès se paramètrent désormais en base (Admin > Modèles de clubs).
 * Les niveaux déjà réglés dans « Droits des clubs » sont repris, puis les clubs existants sont alignés.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_template_slots')) {
            Schema::create('club_template_slots', function (Blueprint $table): void {
                $table->id();
                $table->string('category', 30);
                $table->string('key', 40);
                $table->string('parent_key', 40)->nullable();
                $table->string('kind', 20)->default('position');
                $table->string('title', 120);
                $table->string('capacity', 10)->default('single');
                $table->string('access_level', 30)->default('none');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['category', 'key']);
            });
        }

        $stored = json_decode((string) Setting::query()->where('group', 'org')->where('key', 'club_role_access')->value('value'), true);
        $stored = is_array($stored) ? $stored : [];

        foreach (array_keys(ClubTemplates::CATEGORIES) as $category) {
            if (ClubTemplateSlot::query()->where('category', $category)->exists()) {
                continue;
            }

            ClubTemplates::seed($category, $category === 'regional_club' ? [] : $stored);
        }

        app(ClubTemplates::class)->sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('club_template_slots');
    }
};
