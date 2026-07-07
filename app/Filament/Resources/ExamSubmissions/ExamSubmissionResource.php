<?php

namespace App\Filament\Resources\ExamSubmissions;

use App\Filament\Resources\ExamSubmissions\Pages\AiEssayFeedbackProgress;
use App\Filament\Resources\ExamSubmissions\Pages\CreateExamSubmission;
use App\Filament\Resources\ExamSubmissions\Pages\EditExamSubmission;
use App\Filament\Resources\ExamSubmissions\Pages\ListExamSubmissions;
use App\Filament\Resources\ExamSubmissions\Pages\MonitorExamSessions;
use App\Filament\Resources\ExamSubmissions\Schemas\ExamSubmissionForm;
use App\Filament\Resources\ExamSubmissions\Tables\ExamSubmissionsTable;
use App\Models\ExamSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamSubmissionResource extends Resource
{
    protected static ?string $model = ExamSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Regular admins only see submissions from students enrolled in their sections
        if ($user && $user->is_admin && ! $user->isSuperAdmin()) {
            $query->whereHas('user.sections', fn (Builder $q) => $q->where('admin_id', $user->id));
        }

        return $query;
    }

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return ExamSubmissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamSubmissionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExamSubmissions::route('/'),
            'create' => CreateExamSubmission::route('/create'),
            'edit' => EditExamSubmission::route('/{record}/edit'),
            'monitor' => MonitorExamSessions::route('/monitor/{exam}'),
            'ai-feedback-progress' => AiEssayFeedbackProgress::route('/ai-feedback/{exam}'),
        ];
    }
}
