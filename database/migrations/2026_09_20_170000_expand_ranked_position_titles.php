<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $obsolete = [
            'Instructor I–III', 'Assistant Professor I–IV', 'Associate Professor I–V',
            'Professor I–VI', 'Administrative Officer I–V', 'Librarian I–III',
        ];

        DB::table('positions')->whereIn('name', $obsolete)->delete();

        $names = [
            'Instructor I', 'Instructor II', 'Instructor III',
            'Assistant Professor I', 'Assistant Professor II', 'Assistant Professor III', 'Assistant Professor IV',
            'Associate Professor I', 'Associate Professor II', 'Associate Professor III', 'Associate Professor IV', 'Associate Professor V',
            'Professor I', 'Professor II', 'Professor III', 'Professor IV', 'Professor V', 'Professor VI',
            'Administrative Officer I', 'Administrative Officer II', 'Administrative Officer III', 'Administrative Officer IV', 'Administrative Officer V',
            'Librarian I', 'Librarian II', 'Librarian III',
        ];

        foreach ($names as $order => $name) {
            DB::table('positions')->insertOrIgnore([
                'name' => $name,
                'category' => in_array($name, ['Administrative Officer I', 'Administrative Officer II', 'Administrative Officer III', 'Administrative Officer IV', 'Administrative Officer V'], true)
                    ? 'Administrative Offices'
                    : (str_starts_with($name, 'Librarian') ? 'Library' : 'Academic Officials'),
                'is_active' => true,
                'sort_order' => $order + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // A position is catalog data. Do not remove titles HR may have used.
    }
};
