<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_mode_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode_slug', 50);
            $table->timestamp('used_at')->useCurrent();

            $table->index(['user_id', 'used_at']);
            $table->index('mode_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_mode_usages');
    }
};
