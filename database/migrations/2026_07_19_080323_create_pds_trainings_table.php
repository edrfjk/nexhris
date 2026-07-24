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
    Schema::create('pds_trainings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->date('date_from');
        $table->date('date_to')->nullable();
        $table->decimal('number_of_hours', 8, 2)->nullable();
        $table->string('type')->nullable(); // Managerial/Supervisory/Technical/etc.
        $table->string('conducted_sponsored_by')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_trainings');
    }
};
