<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 12)->unique();
            $table->string('name', 180);
            $table->string('subtitle', 240)->nullable();
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('organizer', 180)->nullable();
            $table->string('venue', 240)->nullable();
            $table->string('time_zone', 80)->default('America/Santo_Domingo');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 30)->default('Draft');
            $table->string('public_access_mode', 30)->default('Device');
            $table->string('results_visibility', 30)->default('PublishedOnly');
            $table->unsignedInteger('presentation_duration_seconds')->default(300);
            $table->unsignedInteger('voting_duration_seconds')->default(180);
            $table->boolean('allow_public_vote_edit')->default(false);
            $table->boolean('allow_juror_vote_edit')->default(true);
            $table->boolean('require_quorum_to_publish')->default(true);
            $table->uuid('template_id')->nullable();
            $table->uuid('active_presentation_id')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('event_brandings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->string('logo_url', 500)->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->string('primary_color', 20)->default('#042E80');
            $table->string('secondary_color', 20)->default('#FEA203');
            $table->string('accent_color', 20)->default('#0C58C7');
            $table->string('background_color', 20)->default('#F4F7FB');
            $table->string('text_color', 20)->default('#17233D');
            $table->string('heading_font', 80)->default('Montserrat');
            $table->string('body_font', 80)->default('Inter');
            $table->string('footer_text', 300)->default('INNOVATEP · Innovación que transforma');
        });

        Schema::create('voting_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('role_type', 20);
            $table->decimal('weight', 8, 6);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('minimum_votes')->nullable();
            $table->decimal('minimum_participation_percent', 8, 4)->nullable();
            $table->boolean('require_all_jurors')->default(false);
            $table->boolean('allow_edit_until_close')->default(false);
            $table->boolean('show_live_progress')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(['event_id', 'name']);
        });

        Schema::create('criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('voting_group_id')->constrained('voting_groups')->cascadeOnDelete();
            $table->unsignedInteger('rubric_version')->default(1);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('control_type', 30)->default('NumericScale');
            $table->decimal('scale_min', 12, 4)->default(1);
            $table->decimal('scale_max', 12, 4)->default(5);
            $table->string('minimum_label', 80)->nullable();
            $table->string('maximum_label', 80)->nullable();
            $table->decimal('weight', 8, 6);
            $table->boolean('required')->default(true);
            $table->string('comment_mode', 20)->default('Hidden');
            $table->string('help_text', 500)->nullable();
            $table->unsignedInteger('sort_order');
            $table->boolean('enabled')->default(true);
            $table->unique(['voting_group_id', 'rubric_version', 'sort_order']);
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('name', 180);
            $table->string('project_title', 240)->nullable();
            $table->text('members')->nullable();
            $table->string('area', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('presentation_order');
            $table->string('status', 30)->default('Active');
            $table->text('disqualification_reason')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'number']);
            $table->unique(['event_id', 'presentation_order']);
        });

        Schema::create('presentations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('participant_id')->unique()->constrained('participants')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status', 30)->default('Pending');
            $table->timestamp('stage_started_at')->nullable();
            $table->timestamp('stage_ended_at')->nullable();
            $table->timestamp('voting_opened_at')->nullable();
            $table->timestamp('voting_closed_at')->nullable();
            $table->timestamp('timer_paused_at')->nullable();
            $table->unsignedInteger('paused_timer_seconds')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->string('operator_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->unique(['event_id', 'sequence']);
        });

        Schema::create('jurors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('title', 180)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('code_hash', 255);
            $table->string('status', 30)->default('Active');
            $table->decimal('individual_weight', 8, 4)->default(1);
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_access_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('voters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('mode', 30);
            $table->string('display_name', 180)->nullable();
            $table->string('external_id', 180)->nullable();
            $table->string('code_hash', 255)->nullable();
            $table->string('device_hash', 100)->nullable();
            $table->string('status', 30)->default('Active');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('last_access_at')->useCurrent();
            $table->index(['event_id', 'device_hash']);
            $table->unique(['event_id', 'external_id']);
        });

        Schema::create('event_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('actor_type', 20);
            $table->uuid('actor_id');
            $table->string('token_hash', 100)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->string('user_agent_hash', 100)->nullable();
            $table->string('device_hash', 100)->nullable();
        });

        Schema::create('votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('presentation_id')->constrained('presentations')->restrictOnDelete();
            $table->foreignUuid('participant_id')->constrained('participants')->restrictOnDelete();
            $table->foreignUuid('voting_group_id')->constrained('voting_groups')->restrictOnDelete();
            $table->string('actor_key', 80);
            $table->uuid('actor_id');
            $table->string('role_type', 20);
            $table->unsignedInteger('rubric_version');
            $table->decimal('raw_score', 12, 4);
            $table->decimal('normalized_score', 12, 4);
            $table->string('status', 30)->default('Submitted');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->uuid('session_id');
            $table->string('client_request_id', 100)->unique();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidated_by')->nullable();
            $table->text('invalidation_reason')->nullable();
            $table->unique(['presentation_id', 'actor_key']);
        });

        Schema::create('vote_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vote_id')->constrained('votes')->cascadeOnDelete();
            $table->foreignUuid('criterion_id')->constrained('criteria')->restrictOnDelete();
            $table->decimal('raw_value', 12, 4);
            $table->decimal('normalized_value', 12, 4);
            $table->decimal('criterion_weight', 8, 6);
            $table->decimal('weighted_value', 12, 4);
            $table->text('comment')->nullable();
            $table->unique(['vote_id', 'criterion_id']);
        });

        Schema::create('vote_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vote_id')->constrained('votes')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->text('snapshot_json');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['vote_id', 'revision_number']);
        });

        Schema::create('results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('participant_id')->constrained('participants')->cascadeOnDelete();
            $table->foreignUuid('voting_group_id')->nullable()->constrained('voting_groups')->cascadeOnDelete();
            $table->decimal('score', 12, 4);
            $table->unsignedInteger('vote_count');
            $table->boolean('quorum_met');
            $table->unsignedInteger('rank')->nullable();
            $table->string('tie_status', 30)->default('None');
            $table->timestamp('calculated_at')->useCurrent();
            $table->string('calculation_version', 50)->default('1.0');
            $table->timestamp('validated_at')->nullable();
            $table->string('validated_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->index(['event_id', 'participant_id', 'voting_group_id']);
        });

        Schema::create('audit_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->timestamp('timestamp')->useCurrent();
            $table->string('actor_type', 50);
            $table->string('actor_id')->nullable();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->string('entity_id', 100)->nullable();
            $table->text('previous_value_json')->nullable();
            $table->text('new_value_json')->nullable();
            $table->string('request_id', 100);
            $table->text('note')->nullable();
            $table->string('technical_fingerprint', 100)->nullable();
            $table->index(['event_id', 'timestamp']);
        });

        Schema::create('event_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 180);
            $table->text('description')->nullable();
            // TEXT defaults are rejected by several MySQL/MariaDB versions.
            // The seeder supplies the empty JSON object for new templates.
            $table->text('configuration_json')->nullable();
            $table->text('branding_json')->nullable();
            $table->timestamps();
            $table->boolean('active')->default(true);
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 120)->primary();
            $table->text('value');
            $table->timestamp('updated_at')->useCurrent();
            $table->string('updated_by')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['app_settings', 'event_templates', 'audit_entries', 'results', 'vote_revisions', 'vote_details', 'votes', 'event_sessions', 'voters', 'jurors', 'presentations', 'participants', 'criteria', 'voting_groups', 'event_brandings', 'events'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
