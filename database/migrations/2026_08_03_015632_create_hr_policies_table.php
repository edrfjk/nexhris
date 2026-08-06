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
    Schema::create('hr_policies', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('category')->nullable(); // e.g. "Leave", "Conduct", "Benefits"
        $table->enum('type', ['text', 'file']);
        $table->longText('body')->nullable();        // for type = text
        $table->string('file_path')->nullable();      // for type = file
        $table->string('file_original_name')->nullable();
        $table->boolean('is_published')->default(false);
        $table->timestamp('published_at')->nullable();
        $table->foreignId('created_by')->constrained('users');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_policies');
    }
};
