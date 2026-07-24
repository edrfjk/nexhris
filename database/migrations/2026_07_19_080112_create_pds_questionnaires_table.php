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
    Schema::create('pds_questionnaires', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

        $table->boolean('related_third_degree')->default(false);
        $table->string('related_third_degree_details')->nullable();
        $table->boolean('related_fourth_degree')->default(false);
        $table->string('related_fourth_degree_details')->nullable();

        $table->boolean('found_admin_guilty')->default(false);
        $table->string('found_admin_guilty_details')->nullable();

        $table->boolean('criminally_charged')->default(false);
        $table->string('criminally_charged_details')->nullable();
        $table->date('criminally_charged_date_filed')->nullable();
        $table->string('criminally_charged_status')->nullable();

        $table->boolean('convicted_crime')->default(false);
        $table->string('convicted_crime_details')->nullable();

        $table->boolean('separated_from_service')->default(false);
        $table->string('separated_from_service_details')->nullable();

        $table->boolean('candidate_in_election')->default(false);
        $table->string('candidate_in_election_details')->nullable();
        $table->boolean('resigned_before_election')->default(false);
        $table->string('resigned_before_election_details')->nullable();

        $table->boolean('acquired_immigrant_status')->default(false);
        $table->string('acquired_immigrant_status_country')->nullable();

        $table->boolean('is_indigenous_group_member')->default(false);
        $table->string('indigenous_group_details')->nullable();
        $table->boolean('is_pwd')->default(false);
        $table->string('pwd_id_no')->nullable();
        $table->boolean('is_solo_parent')->default(false);
        $table->string('solo_parent_id_no')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_questionnaires');
    }
};
