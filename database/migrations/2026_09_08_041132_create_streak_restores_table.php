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
        Schema::create('streak_restores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->date('restored_date');
            $table->unsignedInteger('cost_xp');
            $table->unsignedTinyInteger('sequence_in_month');
            $table->timestamp('restored_at');
            $table->timestamps();

            // One restore per date, ever — the final concurrency guard.
            // Even requests arriving on different Octane workers cannot
            // restore the same calendar day twice.
            $table->unique(['user_id', 'restored_date']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('streak_restores');
    }
};
