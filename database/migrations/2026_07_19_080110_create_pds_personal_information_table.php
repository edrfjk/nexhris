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
    Schema::create('pds_personal_information', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

        $table->string('surname');
        $table->string('first_name');
        $table->string('middle_name')->nullable();
        $table->string('name_extension')->nullable();
        $table->date('date_of_birth');
        $table->string('place_of_birth');
        $table->enum('sex', ['Male', 'Female']);
        $table->enum('civil_status', ['Single', 'Married', 'Widowed', 'Separated', 'Solo Parent', 'Others']);
        $table->string('civil_status_others')->nullable();
        $table->decimal('height_m', 4, 2)->nullable();
        $table->decimal('weight_kg', 5, 2)->nullable();
        $table->string('blood_type')->nullable();

        $table->string('citizenship')->default('Filipino');
        $table->boolean('is_dual_citizen')->default(false);
        $table->string('dual_citizenship_country')->nullable();

        $table->string('gsis_umid_no')->nullable();
        $table->string('pagibig_no')->nullable();
        $table->string('philhealth_no')->nullable();
        $table->string('sss_no')->nullable();
        $table->string('psn_no')->nullable();
        $table->string('tin_no')->nullable();
        $table->string('agency_employee_no')->nullable();

        // Residential address
        $table->string('res_house_block_lot')->nullable();
        $table->string('res_street')->nullable();
        $table->string('res_subdivision_village')->nullable();
        $table->string('res_barangay')->nullable();
        $table->string('res_city_municipality')->nullable();
        $table->string('res_province')->nullable();
        $table->string('res_zip_code')->nullable();

        // Permanent address
        $table->string('perm_house_block_lot')->nullable();
        $table->string('perm_street')->nullable();
        $table->string('perm_subdivision_village')->nullable();
        $table->string('perm_barangay')->nullable();
        $table->string('perm_city_municipality')->nullable();
        $table->string('perm_province')->nullable();
        $table->string('perm_zip_code')->nullable();

        $table->string('telephone_no')->nullable();
        $table->string('mobile_no')->nullable();
        $table->string('email_address')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_personal_information');
    }
};
