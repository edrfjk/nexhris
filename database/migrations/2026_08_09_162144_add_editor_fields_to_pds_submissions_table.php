<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
// add_editor_fields_to_pds_submissions_table
public function up(): void
{
    Schema::table('pds_submissions', function (Blueprint $table) {
        $table->foreignId('pds_template_id')->nullable()->after('user_id')->constrained();
        $table->string('file_path')->nullable()->after('pds_template_id');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pds_submissions', function (Blueprint $table) {
            //
        });
    }
};
