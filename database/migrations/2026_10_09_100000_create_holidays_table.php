<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The holiday calendar HR maintains from official proclamations. One row per
 * dated holiday: dates move every year, so nothing here recurs on its own.
 * Purely additive — no existing leave record is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->date('date');
            // regular, special_non_working, special_local, special_working, religious
            $table->string('category', 30);
            // national, or local to the place named in `locality`
            $table->string('scope', 20)->default('national');
            $table->string('locality', 150)->nullable();
            // Whether the day is left out of chargeable VL/SL days. Stored per
            // holiday so HR decides, rather than the category alone.
            $table->boolean('excludes_from_leave')->default(true);
            // The proclamation or issuance the date comes from.
            $table->string('source', 255)->nullable();
            $table->timestamps();

            $table->unique(['date', 'name']);
            $table->index(['date', 'excludes_from_leave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
