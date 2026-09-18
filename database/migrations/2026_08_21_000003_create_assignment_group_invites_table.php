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
     * One LIVE invite per student per assignment is enforced by the service
     * inside the send transaction (AssignmentInviteService::assertInvitable).
     * A partial unique index ("... WHERE status = pending") would be the
     * belt-and-braces version, but Laravel's SQLite grammar silently drops
     * the WHERE clause — turning it into a full unique index that blocks
     * re-invites. So: plain index + service-level invariant.
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
            $table->index(['assignment_id', 'invitee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_group_invites');
    }
};
