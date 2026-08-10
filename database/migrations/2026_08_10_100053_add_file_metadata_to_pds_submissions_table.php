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
    Schema::table('pds_submissions', function (Blueprint $table) {
        $table->string('file_original_name')->nullable()->after('file_path');
        $table->timestamp('uploaded_at')->nullable()->after('file_original_name');
    });
}

public function down(): void
{
    Schema::table('pds_submissions', function (Blueprint $table) {
        $table->dropColumn(['file_original_name', 'uploaded_at']);
    });
}
};
