<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('provider', 32);
            $table->string('model', 191)->nullable();
            $table->string('source', 32)->default('chat'); // chat | grading | generation
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('neurons', 12, 2)->unsigned()->nullable();
            $table->timestamps();

            $table->index(['date', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
