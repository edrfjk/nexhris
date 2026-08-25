<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) { $table->string('role')->default('employee')->change(); });
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->string('file_path')->nullable();
            foreach (['dean_id','hr_id','director_id'] as $key) $table->foreignId($key)->nullable()->constrained('users')->nullOnDelete();
            foreach (['dean_status','hr_status','director_status'] as $key) $table->string($key)->default('pending');
            foreach (['dean_reviewed_at','hr_reviewed_at','director_reviewed_at'] as $key) $table->timestamp($key)->nullable();
            foreach (['dean_remarks','hr_remarks','director_remarks'] as $key) $table->text($key)->nullable();
        });
    }
    public function down(): void {
        Schema::table('leave_applications', function (Blueprint $table) {
            foreach (['dean_id','hr_id','director_id'] as $key) $table->dropForeign([$key]);
            $table->dropColumn(array_merge(['file_path'], ['dean_id','hr_id','director_id'], ['dean_status','hr_status','director_status'], ['dean_reviewed_at','hr_reviewed_at','director_reviewed_at'], ['dean_remarks','hr_remarks','director_remarks']));
        });
    }
};
