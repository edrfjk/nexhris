<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR announcements, visible to everyone. Kept separate from HR policies:
 * a policy is a standing document that must be acknowledged, whereas an
 * announcement is a dated notice that simply needs to be seen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('category', 60)->nullable();
            // Pinned notices stay at the top of the feed regardless of date.
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            // A notice can be aimed at one college, or left campus-wide.
            $table->foreignId('college_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
