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
    Schema::create('pds_civil_service_eligibilities', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('eligibility_name');
        $table->string('rating')->nullable();
        $table->date('exam_date')->nullable();
        $table->string('exam_place')->nullable();
        $table->string('license_number')->nullable();
        $table->date('license_valid_until')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_civil_service_eligibilities');
    }
};
