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
    Schema::create('leave_ledger_entries', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->date('period_from');
        $table->date('period_to');
        $table->string('remarks')->nullable(); // e.g. "Earned during Midyear 2025", "VL - May 18-19"
        $table->enum('type', ['opening_balance', 'earned', 'leave_deduction', 'adjustment'])->default('earned');

        $table->decimal('vl_earned', 8, 3)->default(0);
        $table->decimal('vl_used', 8, 3)->default(0);
        $table->decimal('vl_balance', 8, 3); // snapshot balance after this row

        $table->decimal('sl_earned', 8, 3)->default(0);
        $table->decimal('sl_used', 8, 3)->default(0);
        $table->decimal('sl_balance', 8, 3); // snapshot balance after this row

        $table->foreignId('leave_application_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('encoded_by')->constrained('users');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_ledger_entries');
    }
};
