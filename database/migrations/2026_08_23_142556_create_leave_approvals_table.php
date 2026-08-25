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
    Schema::create('leave_approvals', function (Blueprint $table) {
        $table->id();
        $table->foreignId('leave_application_id')->constrained()->cascadeOnDelete();
        $table->enum('stage', ['dean', 'hr', 'campus_director']);
        $table->foreignId('user_id')->constrained(); // the approver
        $table->enum('action', ['approved', 'returned']);
        $table->text('remarks')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('leave_approvals');
}
};
