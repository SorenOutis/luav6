<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('td_maps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('grid_width')->default(20);
            $table->unsignedInteger('grid_height')->default(12);
            $table->unsignedInteger('tile_size')->default(48);
            $table->json('tiles')->nullable(); // 2D array of 0=blocked,1=path,2=buildable
            $table->json('path_waypoints'); // [[x,y], ...] in grid coords
            $table->string('background_color')->default('#0a0a0a');
            $table->timestamps();
        });

        Schema::create('td_enemies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('hp')->default(100);
            $table->float('speed')->default(1.5); // tiles per second
            $table->unsignedInteger('armor')->default(0);
            $table->unsignedInteger('damage')->default(1); // lives lost on leak
            $table->unsignedInteger('bounty')->default(10); // gold on kill
            $table->unsignedInteger('score')->default(10);
            $table->string('color')->default('#ef4444');
            $table->unsignedInteger('radius')->default(14);
            $table->json('abilities')->nullable(); // e.g. {"flying":true,"regen":2}
            $table->timestamps();
        });

        Schema::create('td_towers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('cost')->default(50);
            $table->unsignedInteger('damage')->default(10);
            $table->float('range')->default(3); // tiles
            $table->float('fire_rate')->default(1); // shots per second
            $table->string('projectile_type')->default('bullet'); // bullet|laser|splash
            $table->float('splash_radius')->default(0); // tiles (0 = single target)
            $table->float('projectile_speed')->default(8); // tiles per second
            $table->string('color')->default('#38bdf8');
            $table->json('upgrades')->nullable(); // [{"cost":75,"damage":18,"range":3.5,...}]
            $table->timestamps();
        });

        Schema::create('td_difficulties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('starting_gold')->default(150);
            $table->unsignedInteger('starting_lives')->default(20);
            $table->float('enemy_hp_mult')->default(1.0);
            $table->float('enemy_speed_mult')->default(1.0);
            $table->float('gold_mult')->default(1.0);
            $table->float('score_mult')->default(1.0);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('td_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('td_map_id')->constrained('td_maps')->cascadeOnDelete();
            $table->foreignId('td_difficulty_id')->constrained('td_difficulties')->cascadeOnDelete();
            $table->unsignedInteger('starting_gold_override')->nullable();
            $table->unsignedInteger('starting_lives_override')->nullable();
            $table->json('allowed_tower_ids')->nullable(); // null = all
            $table->unsignedInteger('reward_score')->default(100);
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('td_waves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('td_level_id')->constrained('td_levels')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('spawn_delay_ms')->default(3000); // delay before wave starts
            $table->unsignedInteger('bonus_gold')->default(25);
            $table->timestamps();
        });

        Schema::create('td_wave_spawns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('td_wave_id')->constrained('td_waves')->cascadeOnDelete();
            $table->foreignId('td_enemy_id')->constrained('td_enemies')->cascadeOnDelete();
            $table->unsignedInteger('count')->default(10);
            $table->unsignedInteger('interval_ms')->default(800);
            $table->unsignedInteger('offset_ms')->default(0);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('td_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('td_level_id')->constrained('td_levels')->cascadeOnDelete();
            $table->string('status')->default('in_progress'); // in_progress|win|lose|abandoned
            $table->unsignedInteger('waves_completed')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('gold_spent')->default(0);
            $table->unsignedInteger('lives_remaining')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('seed')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['td_level_id', 'score']);
        });

        Schema::create('td_player_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('td_level_id')->constrained('td_levels')->cascadeOnDelete();
            $table->unsignedInteger('best_score')->default(0);
            $table->unsignedInteger('best_waves')->default(0);
            $table->unsignedInteger('stars')->default(0); // 0-3
            $table->unsignedInteger('plays')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'td_level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('td_player_progress');
        Schema::dropIfExists('td_runs');
        Schema::dropIfExists('td_wave_spawns');
        Schema::dropIfExists('td_waves');
        Schema::dropIfExists('td_levels');
        Schema::dropIfExists('td_difficulties');
        Schema::dropIfExists('td_towers');
        Schema::dropIfExists('td_enemies');
        Schema::dropIfExists('td_maps');
    }
};
