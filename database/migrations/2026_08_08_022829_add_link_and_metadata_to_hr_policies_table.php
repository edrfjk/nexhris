<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    // MySQL enum columns need a raw statement to add a new value. Other
    // drivers express an enum as a CHECK constraint that cannot be altered
    // in place, so there the column becomes a plain string instead.
    if (DB::getDriverName() === 'mysql') {
        DB::statement("ALTER TABLE hr_policies MODIFY type ENUM('text', 'file', 'link') NOT NULL");
    } else {
        Schema::table('hr_policies', function (Blueprint $table) {
            $table->string('type')->change();
        });
    }

    Schema::table('hr_policies', function (Blueprint $table) {
        $table->string('link_url')->nullable()->after('file_original_name');
        $table->boolean('is_pinned')->default(false)->after('is_published');
        $table->date('effective_date')->nullable()->after('is_pinned');
        $table->date('expiry_date')->nullable()->after('effective_date');
        $table->boolean('requires_acknowledgment')->default(false)->after('expiry_date');
    });
}

public function down(): void
{
    Schema::table('hr_policies', function (Blueprint $table) {
        $table->dropColumn(['link_url', 'is_pinned', 'effective_date', 'expiry_date', 'requires_acknowledgment']);
    });

    if (DB::getDriverName() === 'mysql') {
        DB::statement("ALTER TABLE hr_policies MODIFY type ENUM('text', 'file') NOT NULL");
    }
}
};
