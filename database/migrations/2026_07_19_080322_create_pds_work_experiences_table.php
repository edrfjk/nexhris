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
    Schema::create('pds_work_experiences', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->date('date_from');
        $table->date('date_to')->nullable(); // null = present
        $table->string('position_title');
        $table->string('department_agency_office_company');
        $table->decimal('monthly_salary', 10, 2)->nullable();
        $table->string('salary_grade')->nullable();
        $table->string('status_of_appointment')->nullable();
        $table->boolean('is_government_service')->default(false);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_work_experiences');
    }
};
