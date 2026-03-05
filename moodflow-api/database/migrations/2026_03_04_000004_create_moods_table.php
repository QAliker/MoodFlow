<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('source', ['emoji', 'slider']);
            $table->string('emotion');
            $table->unsignedTinyInteger('energy');
            $table->float('valence', 4);
            $table->unsignedTinyInteger('intensity');
            $table->json('context')->nullable();
            $table->json('tracks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moods');
    }
};
