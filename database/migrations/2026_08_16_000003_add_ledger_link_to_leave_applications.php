<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('leave_applications', function (Blueprint $table) { $table->foreignId('leave_ledger_entry_id')->nullable()->constrained('leave_ledger_entries')->nullOnDelete(); }); }
    public function down(): void { Schema::table('leave_applications', function (Blueprint $table) { $table->dropForeign(['leave_ledger_entry_id']); $table->dropColumn('leave_ledger_entry_id'); }); }
};
