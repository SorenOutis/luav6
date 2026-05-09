<?php

use App\Models\Section;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $periodMap = [
            '1st Quarter Grade' => 'First Semester - 1st Quarter Grade',
            '2nd Quarter Grade' => 'First Semester - 2nd Quarter Grade',
            '3rd Quarter Grade' => 'Second Semester - 1st Quarter Grade',
            '4th Quarter Grade' => 'Second Semester - 2nd Quarter Grade',
        ];

        $seniorHighSectionIds = DB::table('sections')
            ->where('school_level', Section::SCHOOL_LEVEL_SENIOR_HIGH)
            ->pluck('id');

        if ($seniorHighSectionIds->isEmpty()) {
            return;
        }

        foreach ($periodMap as $oldPeriod => $newPeriod) {
            DB::table('grades')
                ->whereIn('section_id', $seniorHighSectionIds)
                ->where('period', $oldPeriod)
                ->update(['period' => $newPeriod]);
        }
    }

    public function down(): void
    {
        $periodMap = [
            'First Semester - 1st Quarter Grade' => '1st Quarter Grade',
            'First Semester - 2nd Quarter Grade' => '2nd Quarter Grade',
            'Second Semester - 1st Quarter Grade' => '3rd Quarter Grade',
            'Second Semester - 2nd Quarter Grade' => '4th Quarter Grade',
        ];

        $seniorHighSectionIds = DB::table('sections')
            ->where('school_level', Section::SCHOOL_LEVEL_SENIOR_HIGH)
            ->pluck('id');

        if ($seniorHighSectionIds->isEmpty()) {
            return;
        }

        foreach ($periodMap as $newPeriod => $oldPeriod) {
            DB::table('grades')
                ->whereIn('section_id', $seniorHighSectionIds)
                ->where('period', $newPeriod)
                ->update(['period' => $oldPeriod]);
        }
    }
};
