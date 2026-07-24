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
    Schema::create('pds_educational_backgrounds', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->enum('level', ['Elementary', 'Secondary', 'Vocational/Trade Course', 'College', 'Graduate Studies']);
        $table->string('school_name');
        $table->string('degree_course')->nullable();
        $table->date('period_from')->nullable();
        $table->date('period_to')->nullable();
        $table->string('highest_level_units')->nullable();
        $table->string('year_graduated')->nullable();
        $table->string('scholarship_honors')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_educational_backgrounds');
    }
};
