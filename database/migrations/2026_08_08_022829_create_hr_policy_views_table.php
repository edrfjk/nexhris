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
    Schema::create('hr_policy_views', function (Blueprint $table) {
        $table->id();
        $table->foreignId('hr_policy_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->timestamp('viewed_at');
        $table->timestamp('acknowledged_at')->nullable();
        $table->timestamps();

        $table->unique(['hr_policy_id', 'user_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('hr_policy_views');
}
};
