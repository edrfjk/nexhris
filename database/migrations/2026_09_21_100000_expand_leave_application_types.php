<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->string('leave_type', 32)->change();
        });
    }

    public function down(): void
    {
        // Keep the expanded storage so rollback cannot discard new filing types.
    }
};
