<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->string('period')->nullable();
            $table->decimal('score', 6, 2);
            $table->decimal('max_score', 6, 2)->default(100);
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'section_id']);
            $table->index(['section_id', 'subject']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
