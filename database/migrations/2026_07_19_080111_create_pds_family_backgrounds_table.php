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
    Schema::create('pds_family_backgrounds', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

        $table->string('spouse_surname')->nullable();
        $table->string('spouse_first_name')->nullable();
        $table->string('spouse_middle_name')->nullable();
        $table->string('spouse_name_extension')->nullable();
        $table->string('spouse_occupation')->nullable();
        $table->string('spouse_employer_business_name')->nullable();
        $table->string('spouse_business_address')->nullable();
        $table->string('spouse_telephone_no')->nullable();

        $table->string('father_surname')->nullable();
        $table->string('father_first_name')->nullable();
        $table->string('father_middle_name')->nullable();
        $table->string('father_name_extension')->nullable();

        $table->string('mother_maiden_surname')->nullable();
        $table->string('mother_first_name')->nullable();
        $table->string('mother_middle_name')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_family_backgrounds');
    }
};
