<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authentication hardening and the audit trail.
 *
 *  - password_reset_tokens: Laravel's standard broker table. It was missing,
 *    so the forgot-password flow could not have worked at all.
 *  - two_factor_challenges: one row per issued email OTP. The code is stored
 *    hashed and single-use, with an attempt counter so a challenge cannot be
 *    brute-forced.
 *  - activity_logs: who did what to which record, from where, and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        Schema::create('two_factor_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Hashed, never stored in the clear — the emailed code is the only
            // plaintext copy that ever exists.
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'consumed_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Null when the actor is unknown — a failed login names no user.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('description')->nullable();
            // Polymorphic target: the record acted upon, when there is one.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('two_factor_challenges');
        Schema::dropIfExists('password_reset_tokens');
    }
};
