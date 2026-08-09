<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
// create_pds_templates_table
public function up(): void
{
    Schema::create('pds_templates', function (Blueprint $table) {
        $table->id();
        $table->string('label'); // e.g. "CS Form 212 (Revised 2026)"
        $table->string('file_path');
        $table->string('original_filename');
        $table->boolean('is_active')->default(false);
        $table->foreignId('uploaded_by')->constrained('users');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_templates');
    }
};
