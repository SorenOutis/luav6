<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_xp_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->date('claim_date');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('streak')->default(0);
            $table->timestamp('claimed_at');
            $table->timestamps();

            // This is the final concurrency guard. Even requests that arrive on
            // different Octane workers cannot award the same calendar day twice.
            $table->unique(['user_id', 'claim_date']);
            $table->index(['season_id', 'claim_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_xp_claims');
    }
};
