<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `assignments.due_date` was a free-text string column, which made
     * overdue checks and sorting unreliable. Convert it to a real datetime,
     * parsing whatever is currently stored and nulling anything unparseable.
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dateTime('due_at')->nullable()->after('description');
        });

        DB::table('assignments')
            ->whereNotNull('due_date')
            ->where('due_date', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($assignments) {
                foreach ($assignments as $assignment) {
                    try {
                        $parsed = Carbon::parse($assignment->due_date);
                    } catch (Throwable) {
                        continue;
                    }

                    DB::table('assignments')
                        ->where('id', $assignment->id)
                        ->update(['due_at' => $parsed->toDateTimeString()]);
                }
            });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->renameColumn('due_at', 'due_date');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->renameColumn('due_date', 'due_at');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->string('due_date')->nullable()->after('description');
        });

        DB::table('assignments')
            ->whereNotNull('due_at')
            ->orderBy('id')
            ->chunkById(200, function ($assignments) {
                foreach ($assignments as $assignment) {
                    DB::table('assignments')
                        ->where('id', $assignment->id)
                        ->update(['due_date' => (string) $assignment->due_at]);
                }
            });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('due_at');
        });
    }
};
