<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->text('education')->nullable();
            $table->text('experience')->nullable();
            $table->string('skills')->nullable();
            $table->string('languages')->nullable();
            $table->json('privacy_settings')->nullable();
            $table->timestamps();
        });

        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('category', 50);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['entity_id', 'slug']);
        });

        Schema::create('governorates', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('institution_type', ['university', 'high_school', 'other']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['institution_type', 'slug']);
        });

        Schema::create('clubs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'active', 'inactive', 'archived'])->default('active');
            $table->timestamps();
            $table->index(['entity_id', 'department_id', 'institution_id', 'governorate_id'], 'clubs_scope_idx');
        });

        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended', 'archived'])->default('pending');
            $table->string('membership_type', 60)->default('member');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['entity_id', 'department_id', 'club_id']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('scope_type', 50)->default('global');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('direct_superior_assignment_id')->nullable()->constrained('role_assignments')->nullOnDelete();
            $table->enum('status', ['pending', 'active', 'inactive', 'ended'])->default('pending');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['entity_id', 'department_id', 'institution_id', 'governorate_id', 'club_id'], 'role_assign_scope_idx');
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['text', 'image', 'link', 'announcement', 'project_update', 'activity_report', 'event_announcement'])->default('text');
            $table->enum('visibility', ['public', 'authenticated', 'entity', 'department', 'club', 'restricted'])->default('authenticated');
            $table->text('content');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['published_at', 'visibility']);
            $table->index(['entity_id', 'department_id', 'club_id', 'governorate_id'], 'posts_scope_idx');
        });

        Schema::create('post_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('private');
            $table->string('path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });

        Schema::create('post_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('reaction_type', ['like', 'support', 'insightful', 'celebrate'])->default('like');
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->index(['post_id', 'created_at']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status', 40)->default('draft');
            $table->text('goals')->nullable();
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_label', 80)->default('member');
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 40)->default('planned');
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('activity_type', 60)->default('general');
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('activity_at')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 40)->default('draft');
            $table->text('outcomes')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('registration_starts_at')->nullable();
            $table->timestamp('registration_ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('approval_required')->default(false);
            $table->string('status', 40)->default('draft');
            $table->timestamps();
            $table->index(['starts_at', 'status']);
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'confirmed', 'waitlisted', 'rejected', 'cancelled', 'attended', 'absent'])->default('pending');
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'status']);
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_type', 80);
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('approvable_type', 120);
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('assigned_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['request_type', 'status']);
            $table->index(['approvable_type', 'approvable_id']);
        });

        Schema::create('approval_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decided_by')->constrained('users')->cascadeOnDelete();
            $table->enum('decision', ['approved', 'rejected']);
            $table->text('explanation')->nullable();
            $table->timestamps();
            $table->index(['approval_request_id', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 120);
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('media_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 40)->default('private');
            $table->string('disk')->default('private');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
            $table->index(['visibility', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 120);
            $table->string('target_type', 120)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });

        Schema::create('moderation_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('reportable_type', 120);
            $table->unsignedBigInteger('reportable_id');
            $table->text('reason');
            $table->string('status', 40)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 80)->default('general');
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('moderation_reports');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('approval_decisions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_reactions');
        Schema::dropIfExists('post_attachments');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('clubs');
        Schema::dropIfExists('institutions');
        Schema::dropIfExists('governorates');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('entities');
        Schema::dropIfExists('profiles');
    }
};
