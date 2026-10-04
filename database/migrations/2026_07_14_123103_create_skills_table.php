<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('profile_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('profile_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('skill_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('proficiency')->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_skill');
        Schema::dropIfExists('skills');
    }
};
