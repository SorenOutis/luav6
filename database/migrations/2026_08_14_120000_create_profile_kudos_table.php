<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_kudos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 24);
            $table->timestamps();

            $table->unique(['sender_id', 'recipient_id']);
            $table->index(['recipient_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_kudos');
    }
};
