<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The PDS can now be filled in on screen instead of in a downloaded workbook.
 *
 * The answers are kept as one document per submission rather than spread over
 * normalised tables: a PDS is always read and written whole, printed into a
 * fixed form, and never queried field by field. Keeping it on the yearly
 * submission also gives each year its own copy, which next year's form starts
 * from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pds_submissions', function (Blueprint $table) {
            $table->json('form_data')->nullable()->after('return_remarks');
            $table->timestamp('form_updated_at')->nullable()->after('form_data');
        });
    }

    public function down(): void
    {
        Schema::table('pds_submissions', function (Blueprint $table) {
            $table->dropColumn(['form_data', 'form_updated_at']);
        });
    }
};
