<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consolidates the two competing leave-workflow drafts into one schema.
     *
     *  - `leave_form_templates` becomes the single template table; the earlier
     *    `leave_templates` duplicate is dropped (it was never populated).
     *  - The official ISPSC ledger card splits leave taken into "absence /
     *    undertime WITH pay" and "WITHOUT pay", so the single `*_used` column
     *    gains a `*_used_wop` sibling. Existing `*_used` values keep their
     *    meaning as the with-pay column.
     *  - Users gain the "first day of government service" the ledger card
     *    prints in its header.
     *  - Service records grow the fields of the CSC service-record form.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_day_of_service')) {
                $table->date('first_day_of_service')->nullable()->after('position');
            }
        });

        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_ledger_entries', 'vl_used_wop')) {
                $table->decimal('vl_used_wop', 8, 2)->default(0)->after('vl_used');
            }
            if (! Schema::hasColumn('leave_ledger_entries', 'sl_used_wop')) {
                $table->decimal('sl_used_wop', 8, 2)->default(0)->after('sl_used');
            }
            if (! Schema::hasColumn('leave_ledger_entries', 'year_label')) {
                $table->string('year_label', 20)->nullable()->after('period_to');
            }
        });

        Schema::table('service_records', function (Blueprint $table) {
            foreach ([
                'designation' => fn () => $table->string('designation')->nullable(),
                'station' => fn () => $table->string('station')->nullable(),
                'branch' => fn () => $table->string('branch')->nullable(),
                'salary' => fn () => $table->decimal('salary', 12, 2)->nullable(),
                'lwop_days' => fn () => $table->decimal('lwop_days', 8, 2)->default(0),
                'separation_date' => fn () => $table->date('separation_date')->nullable(),
                'separation_cause' => fn () => $table->string('separation_cause')->nullable(),
            ] as $column => $add) {
                if (! Schema::hasColumn('service_records', $column)) {
                    $add();
                }
            }
        });

        // The duplicate template table from the first draft. Empty by design —
        // the active template now lives in `leave_form_templates`.
        Schema::dropIfExists('leave_templates');
    }

    public function down(): void
    {
        Schema::create('leave_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_path');
            $table->boolean('active')->default(true);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        Schema::table('service_records', function (Blueprint $table) {
            $table->dropColumn([
                'designation', 'station', 'branch', 'salary',
                'lwop_days', 'separation_date', 'separation_cause',
            ]);
        });

        Schema::table('leave_ledger_entries', function (Blueprint $table) {
            $table->dropColumn(['vl_used_wop', 'sl_used_wop', 'year_label']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('first_day_of_service');
        });
    }
};
