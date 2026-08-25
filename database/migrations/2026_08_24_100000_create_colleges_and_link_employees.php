<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Colleges become real records instead of a config array plus free-text
 * `department` strings on users.
 *
 * This is the foundation for the Dean's data boundary: every scoped query —
 * approval queues, the leave calendar, dashboards — filters on `college_id`
 * server-side. A string column could not carry a foreign key, so scoping had
 * to be re-derived (and could drift) at each call site.
 *
 * Existing `department` codes are backfilled onto the new rows so no employee
 * loses their college. The old columns are kept for now; a later migration can
 * drop them once nothing reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colleges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->text('description')->nullable();
            // The Dean who signs this college's leave forms. Nullable so a
            // college can exist before its Dean account is created.
            $table->foreignId('dean_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'college_id')) {
                $table->foreignId('college_id')->nullable()->after('department')
                    ->constrained('colleges')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'date_hired')) {
                $table->date('date_hired')->nullable()->after('first_day_of_service');
            }
        });

        // Seed from the config the app has been using, then attach employees.
        $colleges = config('colleges', []);

        foreach ($colleges as $code => $college) {
            $id = DB::table('colleges')->insertGetId([
                'code' => $code,
                'name' => $college['name'] ?? $code,
                'short_name' => $code,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('department', $code)->update(['college_id' => $id]);
        }

        // Any department string that was not in the config still deserves a
        // college row, rather than silently orphaning those employees.
        $orphans = DB::table('users')
            ->whereNotNull('department')
            ->whereNull('college_id')
            ->distinct()
            ->pluck('department');

        foreach ($orphans as $code) {
            if ($code === '' || $code === null) {
                continue;
            }

            $id = DB::table('colleges')->insertGetId([
                'code' => substr($code, 0, 20),
                'name' => $code,
                'short_name' => substr($code, 0, 20),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('department', $code)->update(['college_id' => $id]);
        }

        // Promote each Dean onto the college they already covered.
        $deans = DB::table('users')->where('role', 'dean')->whereNotNull('college_id')->get();

        foreach ($deans as $dean) {
            DB::table('colleges')->where('id', $dean->college_id)->update(['dean_id' => $dean->id]);
        }

        // Existing service date doubles as the hire date until HR edits it.
        DB::statement('UPDATE users SET date_hired = first_day_of_service WHERE date_hired IS NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['college_id']);
            $table->dropColumn(['college_id', 'date_hired']);
        });

        Schema::dropIfExists('colleges');
    }
};
