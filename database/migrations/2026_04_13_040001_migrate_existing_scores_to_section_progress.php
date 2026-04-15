<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('section_progress')->exists()) {
            return;
        }

        $examSubmissions = DB::table('exam_submissions')
            ->join('exams', 'exam_submissions.exam_id', '=', 'exams.id')
            ->whereNotNull('exam_submissions.score')
            ->where('exam_submissions.score', '>', 0)
            ->whereNotNull('exams.section_id')
            ->select('exam_submissions.user_id', 'exams.section_id', 'exam_submissions.score')
            ->get();

        $grouped = $examSubmissions->groupBy(fn($item) => $item->user_id . '_' . $item->section_id)
            ->map(function ($items) {
                return [
                    'user_id' => $items->first()->user_id,
                    'section_id' => $items->first()->section_id,
                    'total_score' => $items->sum('score'),
                ];
            })
            ->values();

        foreach ($grouped as $entry) {
            $existing = DB::table('section_progress')
                ->where('user_id', $entry['user_id'])
                ->where('section_id', $entry['section_id'])
                ->first();

            if ($existing) {
                DB::table('section_progress')
                    ->where('id', $existing->id)
                    ->update([
                        'exp' => $existing->exp + $entry['total_score'],
                        'points' => $existing->points + $entry['total_score'],
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('section_progress')->insert([
                    'user_id' => $entry['user_id'],
                    'section_id' => $entry['section_id'],
                    'exp' => $entry['total_score'],
                    'points' => $entry['total_score'],
                    'level' => floor($entry['total_score'] / 100) + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $affectedUsers = $grouped->pluck('user_id')->unique()->count();
        echo "Migrated scores for {$affectedUsers} users across {$grouped->count()} section progress records.\n";
    }

    public function down(): void
    {
    }
};