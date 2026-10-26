<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('org_nodes', 'join_token')) {
            Schema::table('org_nodes', function (Blueprint $table): void {
                $table->string('join_token', 40)->nullable()->unique()->after('role_key');
            });
        }

        // Un lien d'inscription par poste existant (les nouveaux postes en recoivent un a leur creation).
        DB::table('org_nodes')->where('kind', 'position')->whereNull('join_token')->orderBy('id')
            ->each(fn ($node) => DB::table('org_nodes')->where('id', $node->id)->update(['join_token' => Str::random(40)]));

        if (! Schema::hasTable('position_applications')) {
            Schema::create('position_applications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('node_id')->constrained('org_nodes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('status', 20)->default('pending')->index();
                $table->text('message')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_note')->nullable();
                $table->timestamps();

                $table->index(['node_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('position_applications');

        if (Schema::hasColumn('org_nodes', 'join_token')) {
            Schema::table('org_nodes', function (Blueprint $table): void {
                $table->dropUnique(['join_token']);
                $table->dropColumn('join_token');
            });
        }
    }
};
