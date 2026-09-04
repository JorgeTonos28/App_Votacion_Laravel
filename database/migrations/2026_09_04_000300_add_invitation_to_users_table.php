<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('invitation_token', 100)->nullable()->unique();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->string('status', 30)->default('Active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invitation_token', 'invitation_expires_at', 'status']);
        });
    }
};
