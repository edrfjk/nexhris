<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two timestamp columns were being rewritten by the database on every update.
 *
 * MySQL and MariaDB, with explicit_defaults_for_timestamp off — which is
 * MariaDB's own default, and so what a fresh Ubuntu server gets — attach
 * DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP to the first TIMESTAMP
 * column in a table when the column is declared NOT NULL with no default. Both
 * of these were declared exactly that way.
 *
 * two_factor_challenges.expires_at
 *     A mistyped code increments the attempt counter, which is an UPDATE, so
 *     the database set the expiry to that moment. The very next attempt — the
 *     correct code — was then refused as expired. Anyone with a role that
 *     requires two-factor who made a single typo was locked out of that code.
 *
 * hr_policy_views.viewed_at
 *     Acknowledging a policy updates the same row, so the time the employee
 *     first opened the policy was overwritten with the time they acknowledged
 *     it. The compliance record showed every policy read and acknowledged in
 *     the same second.
 *
 * Declaring each column nullable with an explicit NULL default is what makes
 * the database stop attaching the clause. The application always sets both
 * values itself, so nothing relied on the database filling them in.
 *
 * The test suite runs on SQLite, which has no such behaviour, which is why this
 * was only ever visible against a real MySQL or MariaDB server.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE `two_factor_challenges` MODIFY `expires_at` TIMESTAMP NULL DEFAULT NULL');
        DB::statement('ALTER TABLE `hr_policy_views` MODIFY `viewed_at` TIMESTAMP NULL DEFAULT NULL');
    }

    /**
     * Not reversed. Restoring the auto-update would reintroduce both defects,
     * and no earlier schema depended on it.
     */
    public function down(): void
    {
        //
    }
};
