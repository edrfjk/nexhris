<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LEDGER-03. When HR posts a leave with figures different from the approved
 * form, the difference is recorded here — the approved form itself is no
 * longer overwritten. Append-only: rows are never updated or deleted by the
 * application.
 *
 * Purely additive: one new table, and one nullable column on
 * leave_applications marking postings made since this tracking began. No
 * existing row is changed, and no original value is backfilled — for forms
 * posted earlier it was never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_posting_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_ledger_entry_id')->nullable()->constrained()->nullOnDelete();

            // As approved and certified.
            $table->date('original_date_from')->nullable();
            $table->date('original_date_to')->nullable();
            $table->decimal('original_days', 8, 3);
            $table->json('original_charges');

            // As HR posted them.
            $table->date('posted_date_from');
            $table->date('posted_date_to');
            $table->decimal('posted_days', 8, 3);
            $table->json('posted_charges');

            // Which of the above differ, as the reconciler described them.
            $table->json('differences');
            $table->text('reason');
            $table->foreignId('adjusted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            // Set on every posting from now on. Null on a posted form means it
            // was posted before adjustment tracking existed.
            $table->timestamp('posting_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('posting_checked_at');
        });

        Schema::dropIfExists('leave_posting_adjustments');
    }
};
