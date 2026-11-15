<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes marketing par entité (club, chapitre, projet ou Fondation), identifiant et mot de passe facultatifs
 * (on renseigne d'abord les pages), demandes d'accès aux comptes et chartes d'usage des réseaux sociaux.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketing_accounts', 'scope')) {
            Schema::table('marketing_accounts', function (Blueprint $table): void {
                $table->string('scope', 60)->nullable()->after('club_id');
                $table->index('scope');
            });

            Schema::table('marketing_accounts', function (Blueprint $table): void {
                $table->string('login', 190)->nullable()->change();
                $table->text('password')->nullable()->change();
            });

            // Jusqu'ici, « sans club » désignait la Fondation.
            DB::table('marketing_accounts')->whereNull('club_id')->update(['scope' => 'foundation']);
        }

        if (! Schema::hasTable('marketing_charters')) {
            Schema::create('marketing_charters', function (Blueprint $table): void {
                $table->id();
                $table->string('entity', 80)->unique();
                $table->string('title', 190);
                $table->longText('body');
                $table->unsignedInteger('version')->default(1);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('marketing_access_requests')) {
            Schema::create('marketing_access_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('club_id')->nullable()->constrained('org_nodes')->cascadeOnDelete();
                $table->string('scope', 60)->nullable();
                $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('beneficiary_id')->constrained('users')->cascadeOnDelete();
                $table->json('platforms');
                $table->text('reason')->nullable();
                $table->string('facebook_profile_url', 255);
                $table->string('phone', 40);
                $table->boolean('identity_verified')->default(false);
                $table->string('status', 20)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->unsignedInteger('charter_version')->nullable();
                $table->longText('charter_text')->nullable();
                $table->string('charter_signed_name', 160)->nullable();
                $table->string('charter_signed_ip', 45)->nullable();
                $table->timestamp('charter_signed_at')->nullable();
                $table->string('meta_portfolio', 190)->nullable();
                $table->timestamp('meta_assigned_at')->nullable();
                $table->foreignId('meta_assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('revoked_at')->nullable();
                $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['beneficiary_id', 'status']);
                $table->index(['status', 'created_at']);
                $table->index('club_id');
                $table->index('scope');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_access_requests');
        Schema::dropIfExists('marketing_charters');
    }
};
