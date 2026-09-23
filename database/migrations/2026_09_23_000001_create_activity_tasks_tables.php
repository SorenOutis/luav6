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
        Schema::create('activity_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('term');
            $table->string('task_type')->default('Performance Task');
            $table->decimal('max_points', 8, 2)->default(100.00);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_task_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 8, 2)->nullable();
            $table->boolean('is_missed')->default(false);
            $table->text('remarks')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['activity_task_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_task_scores');
        Schema::dropIfExists('activity_tasks');
    }
};
