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
    Schema::create('pds_declarations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
        $table->string('photo_path')->nullable();
        $table->string('signature_path')->nullable();
        $table->string('government_id_type')->nullable();
        $table->string('government_id_no')->nullable();
        $table->string('id_issuance_date_place')->nullable();
        $table->date('date_accomplished')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_declarations');
    }
};
