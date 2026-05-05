<?php

namespace App\Filament\Resources\LearningMap\MapNodes;

use App\Filament\Resources\LearningMap\MapNodes\Pages\CreateMapNode;
use App\Filament\Resources\LearningMap\MapNodes\Pages\EditMapNode;
use App\Filament\Resources\LearningMap\MapNodes\Pages\ListMapNodes;
use App\Models\Badge;
use App\Models\Exam;
use App\Models\LearningMap\MapNode;
use App\Models\LearningMap\MapNodeRequirement;
use App\Models\LearningMap\MapWorld;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MapNodeResource extends Resource
{
    protected static ?string $model = MapNode::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning Map';

    protected static ?string $navigationLabel = 'Nodes';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('map_world_id')
                ->label('World')
                ->relationship('world', 'name')
                ->options(fn () => MapWorld::orderBy('sort_order')->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload(),

            TextInput::make('title')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => filled($get('slug')) ? null : $set('slug', Str::slug($state))),

            TextInput::make('slug')->required()->unique(ignoreRecord: true),

            Select::make('type')
                ->options([
                    'lesson' => 'Lesson',
                    'exam' => 'Exam',
                    'boss' => 'Boss',
                ])->default('lesson')->required(),

            TextInput::make('x')->label('X Position')->numeric()->default(0)->required(),
            TextInput::make('y')->label('Y Position')->numeric()->default(0)->required(),

            TextInput::make('pass_score')
                ->label('Pass Score (%)')
                ->numeric()->minValue(0)->maxValue(100)
                ->helperText('Leave blank to use the configured default ('.config('gamification.map_node_default_pass_score', 70).'%).'),

            Section::make('Linked Exam')
                ->description('When a user passes this exam with a score ≥ Pass Score, the node is auto-completed.')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Hidden::make('target_type')
                        ->default(Exam::class)
                        ->dehydrateStateUsing(fn ($state, $get) => $get('target_id') ? Exam::class : null),

                    Select::make('target_id')
                        ->label('Exam')
                        ->options(fn () => Exam::orderBy('id', 'desc')->limit(500)->pluck('title', 'id'))
                        ->searchable()
                        ->nullable()
                        ->helperText('Optional. Leave blank for placeholder or non-exam nodes.'),
                ]),

            Section::make('Rewards')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('reward_xp')->numeric()->default(0)->minValue(0),
                    TextInput::make('reward_points')->numeric()->default(0)->minValue(0),
                    Select::make('reward_badge_id')
                        ->label('Reward Badge')
                        ->options(fn () => Badge::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                ]),

            Section::make('Unlock Requirements')
                ->description('All requirements must be met (AND) for the node to become available.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('requirements')
                        ->relationship()
                        ->columns(2)
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => match ($state['kind'] ?? null) {
                            'node' => 'Prereq node: '.($state['target_node_slug'] ?? '—'),
                            'xp' => 'XP: '.($state['amount'] ?? 0),
                            'level' => 'Level: '.($state['level'] ?? 1),
                            'badge' => 'Badge #'.($state['badge_id'] ?? '?'),
                            'streak' => 'Streak: '.($state['amount'] ?? 0).'d',
                            default => 'Requirement',
                        })
                        ->reorderable(false)
                        ->addActionLabel('Add requirement')
                        ->schema([
                            Select::make('kind')
                                ->options([
                                    'node' => 'Previous node completed',
                                    'xp' => 'Total XP',
                                    'level' => 'Minimum level',
                                    'badge' => 'Badge earned',
                                    'streak' => 'Daily streak',
                                ])
                                ->required()
                                ->live(),

                            Select::make('target_node_slug')
                                ->label('Prerequisite node')
                                ->options(fn () => MapNode::orderBy('title')->pluck('title', 'slug'))
                                ->searchable()
                                ->visible(fn ($get) => $get('kind') === 'node'),

                            TextInput::make('min_score')
                                ->label('Min score (%)')
                                ->numeric()->minValue(0)->maxValue(100)
                                ->visible(fn ($get) => $get('kind') === 'node'),

                            TextInput::make('amount')
                                ->label(fn ($get) => $get('kind') === 'streak' ? 'Days' : 'Amount')
                                ->numeric()->minValue(1)
                                ->visible(fn ($get) => in_array($get('kind'), ['xp', 'streak'])),

                            TextInput::make('level')
                                ->numeric()->minValue(1)
                                ->visible(fn ($get) => $get('kind') === 'level'),

                            Select::make('badge_id')
                                ->label('Badge')
                                ->options(fn () => Badge::orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->visible(fn ($get) => $get('kind') === 'badge'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('map_world_id')
            ->columns([
                TextColumn::make('world.name')->label('World')->sortable()->searchable(),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('slug')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')->badge(),
                TextColumn::make('reward_xp')->label('XP'),
                TextColumn::make('pass_score')->label('Pass')->formatStateUsing(fn ($state) => $state ? "{$state}%" : 'default'),
                TextColumn::make('requirements_count')->counts('requirements')->label('Reqs'),
            ])
            ->filters([
                SelectFilter::make('map_world_id')
                    ->label('World')
                    ->options(fn () => MapWorld::orderBy('sort_order')->pluck('name', 'id')),
                SelectFilter::make('type')->options([
                    'lesson' => 'Lesson',
                    'exam' => 'Exam',
                    'boss' => 'Boss',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMapNodes::route('/'),
            'create' => CreateMapNode::route('/create'),
            'edit' => EditMapNode::route('/{record}/edit'),
        ];
    }
}
