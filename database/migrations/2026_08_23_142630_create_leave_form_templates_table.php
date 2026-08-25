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
    Schema::create('leave_form_templates', function (Blueprint $table) {
        $table->id();
        $table->string('label');
        $table->string('file_path');
        $table->string('original_filename');
        $table->boolean('is_active')->default(false);
        $table->foreignId('uploaded_by')->constrained('users');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('leave_form_templates');
}
};
