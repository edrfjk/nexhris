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
    Schema::create('leave_applications', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->enum('leave_type', ['VL', 'SL']);
        $table->date('date_from');
        $table->date('date_to');
        $table->decimal('days', 8, 3);
        $table->text('reason')->nullable();
        $table->enum('status', ['pending', 'approved', 'declined'])->default('pending');
        $table->foreignId('reviewed_by')->nullable()->constrained('users');
        $table->timestamp('reviewed_at')->nullable();
        $table->string('remarks')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
