<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('status');
        $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['failed_login_attempts', 'locked_until']);
    });
}
};
