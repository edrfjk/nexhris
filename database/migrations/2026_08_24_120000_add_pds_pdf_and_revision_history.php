<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDS submissions keep both the workbook and its converted PDF, plus a
 * revision trail.
 *
 * Re-uploading after a return used to overwrite the previous file, so a
 * returned-and-corrected PDS left no record of what HR originally saw. Each
 * upload is now archived as a revision before the current one is replaced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pds_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('pds_submissions', 'pdf_path')) {
                // The converted copy, produced at upload so HR previews a PDF
                // without waiting on LibreOffice mid-review.
                $table->string('pdf_path')->nullable()->after('file_original_name');
            }
            if (! Schema::hasColumn('pds_submissions', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('pdf_path');
            }
            if (! Schema::hasColumn('pds_submissions', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('uploaded_at');
            }
        });

        Schema::hasTable('pds_submission_revisions') or Schema::create('pds_submission_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('pds_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->string('pdf_path')->nullable();
            $table->string('file_original_name')->nullable();
            // The verdict this revision received, and why.
            $table->string('outcome', 32)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['pds_submission_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_submission_revisions');

        Schema::table('pds_submissions', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'version', 'converted_at']);
        });
    }
};
