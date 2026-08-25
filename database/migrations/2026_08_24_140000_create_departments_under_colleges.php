<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Departments (BSIT, BAEL, Registrar …) as real records under a College.
 *
 * They were previously a free-text `users.program` string populated from a
 * hardcoded array in config/colleges.php, so HR could not add one without a
 * code change, and two spellings of the same department never grouped.
 *
 * The old `program` column is left in place and kept in step: the ledger card
 * and several PDFs still read it, and dropping it would break printed output
 * for a cosmetic gain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('college_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            // The programme chair / office head, if the campus names one.
            $table->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // A code need only be unique inside its own college.
            $table->unique(['college_id', 'code']);
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('college_id')
                    ->constrained()->nullOnDelete();
            }
        });

        // Seed from the config the app has been using so nothing is lost.
        foreach (config('colleges', []) as $code => $college) {
            $collegeId = DB::table('colleges')->where('code', $code)->value('id');

            if (! $collegeId) {
                continue;
            }

            foreach ($college['programs'] ?? [] as $program) {
                DB::table('departments')->insertOrIgnore([
                    'college_id' => $collegeId,
                    'code' => static::abbreviate($program),
                    'name' => $program,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Any programme an employee holds that the config never listed still
        // deserves a department, rather than being silently dropped.
        $orphans = DB::table('users')
            ->whereNotNull('program')
            ->where('program', '!=', '')
            ->whereNotNull('college_id')
            ->select('program', 'college_id')
            ->distinct()
            ->get();

        foreach ($orphans as $row) {
            DB::table('departments')->insertOrIgnore([
                'college_id' => $row->college_id,
                'code' => static::abbreviate($row->program),
                'name' => $row->program,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Attach every employee to the department matching their programme.
        foreach (DB::table('departments')->get() as $department) {
            DB::table('users')
                ->where('college_id', $department->college_id)
                ->where('program', $department->name)
                ->update(['department_id' => $department->id]);
        }
    }

    /**
     * "Bachelor of Science in Information Technology" -> "BSIT".
     * Short names are kept whole; anything that collapses to nothing falls
     * back to a truncated slug.
     */
    public static function abbreviate(string $name): string
    {
        $clean = trim($name);

        if (mb_strlen($clean) <= 12) {
            return Str::upper(Str::limit($clean, 30, ''));
        }

        // Skip the joining words a degree title is full of.
        $skip = ['of', 'in', 'the', 'and', 'for', 'a', 'an'];

        $letters = collect(preg_split('/[\s\-\/]+/', $clean))
            ->reject(fn ($word) => $word === '' || in_array(mb_strtolower($word), $skip, true))
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $letters !== ''
            ? Str::limit($letters, 30, '')
            : Str::upper(Str::limit(Str::slug($clean), 30, ''));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
