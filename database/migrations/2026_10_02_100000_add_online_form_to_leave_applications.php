<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave filed on screen.
 *
 * An employee can now fill in CS Form No. 6 in the system instead of
 * uploading a workbook. The answers are kept as data and printed into the
 * official form whenever it is read, so the form shows each reviewer's
 * decision as soon as it is made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            // "upload" for a filled workbook, "online" for the on-screen form.
            $table->string('filing_method', 16)->default('upload')->after('leave_form_template_id');
            // Everything on the form that is not a column of its own.
            $table->json('form_data')->nullable()->after('filing_method');
            // 7.A as HR certified it: the ledger figures when HR approved.
            $table->json('credit_certification')->nullable()->after('form_data');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn(['filing_method', 'form_data', 'credit_certification']);
        });
    }
};
