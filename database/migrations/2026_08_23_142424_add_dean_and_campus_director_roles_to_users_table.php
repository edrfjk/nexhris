<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Dean and Campus Director roles, the two extra signatories in the
 * leave approval chain.
 *
 * MySQL keeps a real ENUM so the database itself rejects a bad role. Other
 * drivers (SQLite under test) express enums as a CHECK constraint that cannot
 * be altered in place, so there the column becomes a plain string and the
 * application's role checks do the validating.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'dean', 'campus_director', 'employee') NOT NULL DEFAULT 'employee'");

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('employee')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee'");

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('employee')->change();
        });
    }
};
