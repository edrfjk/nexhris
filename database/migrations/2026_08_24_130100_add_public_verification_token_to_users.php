<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A random token behind each digital ID's QR code.
 *
 * The public verification page is reachable without signing in, so it must not
 * be addressable by employee id — that would let anyone enumerate staff by
 * counting upwards. The token is unguessable and can be rotated if a card is
 * lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'verification_token')) {
                $table->string('verification_token', 64)->nullable()->unique()->after('remember_token');
            }
        });

        foreach (DB::table('users')->whereNull('verification_token')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['verification_token' => Str::random(40)]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('verification_token');
        });
    }
};
