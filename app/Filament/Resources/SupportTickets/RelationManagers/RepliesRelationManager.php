<?php

namespace App\Filament\Resources\SupportTickets\RelationManagers;

use App\Models\SupportTicketReply;
use App\Services\SupportReplyService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RepliesRelationManager extends RelationManager
{
    protected static string $relationship = 'replies';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Comments / Replies';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Author')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => auth()->id())
                    ->required(),
                Textarea::make('message')
                    ->required()
                    ->maxLength(2000)
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('message')
                    ->limit(80)
                    ->searchable()
                    ->tooltip(fn ($record) => $record->message)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add reply')
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] ??= auth()->id();
                        $data['message'] = trim(strip_tags((string) ($data['message'] ?? '')));

                        return $data;
                    })
                    ->after(function (SupportTicketReply $record): void {
                        app(SupportReplyService::class)->notifyStudentOfReply($record);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
