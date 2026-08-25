<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Template versioning, and each employee's own ledger workbook.
 *
 * Publishing a new template no longer overwrites the old one: every upload is
 * a new numbered version, and a submission records the exact version it was
 * filled on. A form returned six months later can still be read against the
 * template the employee actually downloaded.
 *
 * The ledger works differently. One master workbook is seeded once; the first
 * time HR opens an employee's ledger the system copies the master to
 * ledger_{employee_id}.xlsx and edits that real file from then on, so the
 * campus's formatting and merged cells survive.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pds_templates', 'leave_form_templates'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'version')) {
                    $blueprint->unsignedInteger('version')->default(1)->after('label');
                }
                if (! Schema::hasColumn($table, 'checksum')) {
                    // Lets an identical re-upload be recognised instead of
                    // silently creating a duplicate version.
                    $blueprint->string('checksum', 64)->nullable()->after('original_filename');
                }
                if (! Schema::hasColumn($table, 'superseded_at')) {
                    $blueprint->timestamp('superseded_at')->nullable()->after('is_active');
                }
                if (! Schema::hasColumn($table, 'notes')) {
                    $blueprint->string('notes')->nullable()->after('superseded_at');
                }
            });

            // Number the rows that already exist, oldest first.
            $version = 1;
            foreach (DB::table($table)->orderBy('id')->pluck('id') as $id) {
                DB::table($table)->where('id', $id)->update(['version' => $version++]);
            }
        }

        // A leave form now records which template version it was filled on,
        // matching what pds_submissions already did.
        Schema::table('leave_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_applications', 'leave_form_template_id')) {
                $table->foreignId('leave_form_template_id')->nullable()->after('file_original_name')
                    ->constrained('leave_form_templates')->nullOnDelete();
            }
        });

        // The master ledger workbook. Versioned like the others, but never
        // handed to employees — it is the source every ledger is copied from.
        Schema::hasTable('ledger_templates') or Schema::create('ledger_templates', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->unsignedInteger('version')->default(1);
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('checksum', 64)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('superseded_at')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One workbook per employee, copied from the master on first use.
        Schema::hasTable('employee_ledgers') or Schema::create('employee_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            // Which master version it was copied from, so HR can tell which
            // ledgers predate a template change.
            $table->unsignedInteger('template_version')->nullable();
            $table->timestamp('last_edited_at')->nullable();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Cell-level audit for the ledger: field, old value, new value, who.
        Schema::hasTable('ledger_changes') or Schema::create('ledger_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_ledger_id')->constrained()->cascadeOnDelete();
            $table->string('cell', 16);
            $table->string('sheet')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_ledger_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_changes');
        Schema::dropIfExists('employee_ledgers');
        Schema::dropIfExists('ledger_templates');

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropForeign(['leave_form_template_id']);
            $table->dropColumn('leave_form_template_id');
        });

        foreach (['pds_templates', 'leave_form_templates'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['version', 'checksum', 'superseded_at', 'notes']);
            });
        }
    }
};
