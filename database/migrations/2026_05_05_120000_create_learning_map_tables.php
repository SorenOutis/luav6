<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_worlds', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            // Theme colours (hex). Kept as plain strings for easy Filament editing.
            $table->string('primary_color', 16)->default('#10b981');
            $table->string('accent_color', 16)->default('#34d399');
            $table->string('background_class')->default('bg-emerald-50/30');
            $table->timestamps();
        });

        Schema::create('map_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_world_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->enum('type', ['lesson', 'exam', 'boss'])->default('lesson');
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            // Optional link to an Exam or other polymorphic target. Null = placeholder.
            $table->nullableMorphs('target');
            // Rewards granted when the node is completed.
            $table->unsignedInteger('reward_xp')->default(0);
            $table->unsignedInteger('reward_points')->default(0);
            $table->foreignId('reward_badge_id')->nullable()->constrained('badges')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('map_node_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_node_id')->constrained()->cascadeOnDelete();
            // kind: 'node' | 'xp' | 'level' | 'badge' | 'streak'
            $table->string('kind', 16);
            // Polymorphic payload. Interpretation depends on `kind`.
            $table->string('target_node_slug')->nullable();
            $table->unsignedInteger('amount')->nullable();         // xp amount / streak days
            $table->unsignedInteger('level')->nullable();          // required level
            $table->foreignId('badge_id')->nullable()->constrained('badges')->cascadeOnDelete();
            $table->unsignedTinyInteger('min_score')->nullable();  // for 'node' requirements
            $table->timestamps();

            $table->index(['map_node_id', 'kind']);
        });

        Schema::create('user_map_node_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('map_node_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'map_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_map_node_progress');
        Schema::dropIfExists('map_node_requirements');
        Schema::dropIfExists('map_nodes');
        Schema::dropIfExists('map_worlds');
    }
};
