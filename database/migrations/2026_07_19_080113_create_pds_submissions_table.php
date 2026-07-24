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
    Schema::create('pds_submissions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->year('applicable_year'); // e.g. 2026 - the yearly PDS cycle
        $table->enum('status', ['not_started', 'draft', 'submitted', 'approved', 'returned'])->default('not_started');
        $table->timestamp('submitted_at')->nullable();
        $table->foreignId('reviewed_by')->nullable()->constrained('users');
        $table->timestamp('reviewed_at')->nullable();
        $table->text('return_remarks')->nullable();
        $table->timestamps();

        $table->unique(['user_id', 'applicable_year']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_submissions');
    }
};
