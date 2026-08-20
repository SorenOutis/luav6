<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group formation goes through invites (see GROUP_INVITE_FLOW_DESIGN.md):
     * the creator invites classmates, each invitee accepts or declines, and
     * only accepted invites write group membership. Rows are history — a
     * declined student can be re-invited on a fresh row.
     *
     * One LIVE invite per student per assignment: the partial unique index
     * below only constrains pending rows (Postgres in production and SQLite
     * in tests both support partial indexes).
     */
    public function up(): void
    {
        Schema::create('assignment_group_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('assignment_groups')->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            // Invites expire at the assignment's due date (null due date =
            // no expiry). Enforced lazily by the model sweep.
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->index(['invitee_id', 'status']);
            $table->unique(['assignment_id', 'invitee_id'])->where('status', 'pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_group_invites');
    }
};
