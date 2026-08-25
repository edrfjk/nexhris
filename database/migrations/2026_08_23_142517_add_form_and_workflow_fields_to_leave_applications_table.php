<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    // ============================================================
    // ADD NEW COLUMNS ONLY IF THEY DON'T ALREADY EXIST
    // ============================================================

    Schema::table('leave_applications', function (Blueprint $table) {

        if (!Schema::hasColumn('leave_applications', 'file_path')) {
            $table->string('file_path')
                ->nullable()
                ->after('reason');
        }

        if (!Schema::hasColumn('leave_applications', 'file_original_name')) {
            $table->string('file_original_name')
                ->nullable()
                ->after('file_path');
        }

        if (!Schema::hasColumn('leave_applications', 'uploaded_at')) {
            $table->timestamp('uploaded_at')
                ->nullable()
                ->after('file_original_name');
        }

        if (!Schema::hasColumn('leave_applications', 'ledger_posted')) {
            $table->boolean('ledger_posted')
                ->default(false)
                ->after('remarks');
        }
    });


    // ============================================================
    // WIDEN THE STATUS COLUMN, MIGRATE THE OLD VALUES, THEN NARROW
    // ============================================================
    //
    // MySQL keeps a real ENUM, so it has to accept the old and new values at
    // once while the data is converted. SQLite expresses an enum as a CHECK
    // constraint that cannot be altered in place, so there the column simply
    // becomes a string and the workflow code is what validates the value.

    $isMysql = DB::getDriverName() === 'mysql';

    if ($isMysql) {
        DB::statement("ALTER TABLE leave_applications MODIFY status ENUM(
            'pending',
            'approved',
            'declined',
            'draft',
            'submitted',
            'dean_approved',
            'dean_returned',
            'hr_approved',
            'hr_returned',
            'cd_approved',
            'cd_returned',
            'completed'
        ) NOT NULL DEFAULT 'draft'");
    } else {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }

    DB::table('leave_applications')->whereIn('status', ['pending', 'approved', 'declined'])->update([
        'status' => DB::raw("CASE
            WHEN status = 'pending' THEN 'draft'
            WHEN status = 'approved' THEN 'completed'
            ELSE 'dean_returned'
        END"),
    ]);

    if ($isMysql) {
        DB::statement("ALTER TABLE leave_applications MODIFY status ENUM(
            'draft',
            'submitted',
            'dean_approved',
            'dean_returned',
            'hr_approved',
            'hr_returned',
            'cd_approved',
            'cd_returned',
            'completed'
        ) NOT NULL DEFAULT 'draft'");
    }
}

    /**
     * Reverse the migrations.
     */
public function down(): void
{
    $isMysql = DB::getDriverName() === 'mysql';

    if ($isMysql) {
        DB::statement("ALTER TABLE leave_applications MODIFY status ENUM(
            'pending',
            'approved',
            'declined',
            'draft',
            'submitted',
            'dean_approved',
            'dean_returned',
            'hr_approved',
            'hr_returned',
            'cd_approved',
            'cd_returned',
            'completed'
        ) NOT NULL DEFAULT 'pending'");
    } else {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    DB::table('leave_applications')->update([
        'status' => DB::raw("CASE
            WHEN status IN ('draft', 'submitted') THEN 'pending'
            WHEN status IN ('dean_approved', 'hr_approved', 'cd_approved', 'completed') THEN 'approved'
            WHEN status IN ('dean_returned', 'hr_returned', 'cd_returned') THEN 'declined'
            ELSE status
        END"),
    ]);

    if ($isMysql) {
        DB::statement("ALTER TABLE leave_applications MODIFY status ENUM(
            'pending',
            'approved',
            'declined'
        ) NOT NULL DEFAULT 'pending'");
    }


    // ============================================================
    // REMOVE NEW COLUMNS
    // ============================================================

    Schema::table('leave_applications', function (Blueprint $table) {

        if (Schema::hasColumn('leave_applications', 'file_original_name')) {
            $table->dropColumn('file_original_name');
        }

        if (Schema::hasColumn('leave_applications', 'uploaded_at')) {
            $table->dropColumn('uploaded_at');
        }

        if (Schema::hasColumn('leave_applications', 'ledger_posted')) {
            $table->dropColumn('ledger_posted');
        }

        // DON'T DROP file_path if it existed before this migration.
        // Your previous migration attempts show that it already existed.
    });
}
};