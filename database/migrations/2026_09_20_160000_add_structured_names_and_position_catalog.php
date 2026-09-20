<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('category', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $positions = [
            'Academic Officials' => ['Campus Director', 'College Dean', 'Associate Dean', 'Program Chair / Program Head', 'Department Chair', 'Academic Coordinator', 'Instructor I', 'Instructor II', 'Instructor III', 'Assistant Professor I', 'Assistant Professor II', 'Assistant Professor III', 'Assistant Professor IV', 'Associate Professor I', 'Associate Professor II', 'Associate Professor III', 'Associate Professor IV', 'Associate Professor V', 'Professor I', 'Professor II', 'Professor III', 'Professor IV', 'Professor V', 'Professor VI', 'University Professor (SUCs)'],
            'Student Affairs and Services' => ['Director of Student Affairs', 'Guidance Counselor', 'Registrar', 'Scholarship Coordinator', 'Student Organization Adviser', 'Career Placement Officer'],
            'Administrative Offices' => ['Human Resource Management Officer (HRMO)', 'HR Assistant', 'Administrative Officer I', 'Administrative Officer II', 'Administrative Officer III', 'Administrative Officer IV', 'Administrative Officer V', 'Administrative Assistant', 'Supply Officer', 'Property Custodian', 'Budget Officer', 'Accountant', 'Cashier', 'Procurement Officer'],
            'Library' => ['Chief Librarian', 'Librarian I', 'Librarian II', 'Librarian III', 'Library Assistant'],
            'Research and Extension' => ['Director of Research', 'Research Coordinator', 'Extension Coordinator', 'Research Assistant'],
            'Quality Assurance' => ['Quality Assurance Director', 'QA Coordinator', 'Accreditation Officer'],
            'Human Resource Office' => ['Human Resource Management Officer II (HRMO II)', 'Records Officer', 'Training and Development Officer'],
            'Finance Office' => ['Chief Accountant', 'Bookkeeper'],
            'Health Services' => ['School Physician', 'School Dentist', 'School Nurse', 'Medical Technologist'],
            'Security and Maintenance' => ['Security Officer', 'Security Guard', 'Maintenance Supervisor', 'Electrician', 'Plumber', 'Janitor / Utility Worker', 'Groundskeeper'],
        ];

        $order = 1;
        foreach ($positions as $category => $names) {
            foreach ($names as $name) {
                DB::table('positions')->insertOrIgnore([
                    'name' => $name,
                    'category' => $category,
                    'is_active' => true,
                    'sort_order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }
};
