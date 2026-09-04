<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('current_round')->default(1);
        });
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropUnique('presentations_participant_id_unique');
            $table->dropUnique('presentations_event_id_sequence_unique');
            $table->unsignedInteger('round_number')->default(1);
            $table->unique(['event_id', 'round_number', 'participant_id'], 'presentations_event_round_participant_unique');
            $table->unique(['event_id', 'round_number', 'sequence'], 'presentations_event_round_sequence_unique');
        });
        Schema::table('votes', function (Blueprint $table) {
            $table->unsignedInteger('round_number')->default(1);
            $table->index(['event_id', 'round_number']);
        });
        Schema::table('results', function (Blueprint $table) {
            $table->unsignedInteger('round_number')->default(1);
            $table->index(['event_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'round_number']);
            $table->dropColumn('round_number');
        });
        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'round_number']);
            $table->dropColumn('round_number');
        });
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropUnique('presentations_event_round_participant_unique');
            $table->dropUnique('presentations_event_round_sequence_unique');
            $table->dropColumn('round_number');
            $table->unique('participant_id');
            $table->unique(['event_id', 'sequence']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('current_round');
        });
    }
};
