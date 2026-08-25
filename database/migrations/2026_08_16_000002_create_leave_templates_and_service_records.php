<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('leave_templates', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('file_path'); $table->boolean('active')->default(true); $table->foreignId('uploaded_by')->constrained('users'); $table->timestamps(); });
        Schema::create('service_records', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->date('date_from')->nullable(); $table->date('date_to')->nullable(); $table->string('record_type')->nullable(); $table->text('description')->nullable(); $table->string('status')->default('active'); $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); });
    }
    public function down(): void { Schema::dropIfExists('service_records'); Schema::dropIfExists('leave_templates'); }
};
