<?php

namespace App\Filament\Resources\PlatformAiBots\Schemas;

use App\Ai\Support\ToolRegistry;
use App\Models\AiBot;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PlatformAiBotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Identity')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('workspace_id')
                            ->label('Workspace')
                            ->relationship('workspace', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('Platform-wide bot')
                            ->helperText('Leave empty only for a platform bot. Workspace bots see only their own tenant.')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(),
                        Select::make('kind')
                            ->options([
                                AiBot::KIND_SPECIALIST => 'Specialist',
                                AiBot::KIND_COORDINATOR => 'Coordinator (Chief of Staff)',
                            ])
                            ->required()
                            ->live()
                            ->helperText('A workspace can have only one active coordinator.'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(80),
                        TextInput::make('slug')
                            ->maxLength(64)
                            ->helperText('Stable delegation key. Leave blank to derive it from the name.')
                            ->unique(ignoreRecord: true),
                        TextInput::make('title')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('avatar')
                            ->maxLength(255)
                            ->helperText('Emoji or image path.'),
                        Hidden::make('created_by')
                            ->default(fn (): ?int => auth()->id()),
                        Textarea::make('instructions')
                            ->label('Role notes')
                            ->rows(4)
                            ->helperText('Optional notes appended after the structured policy. The policy remains the source of truth.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Structured policy')
                    ->description('The policy is compiled into the bot instructions. Write mode and workspace-only access are locked.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('policy.identity.role')
                            ->label('Role')
                            ->maxLength(255),
                        TextInput::make('policy.identity.mission')
                            ->label('Mission')
                            ->maxLength(500),
                        TextInput::make('policy.identity.tone')
                            ->label('Tone')
                            ->maxLength(255)
                            ->default('concise, professional, plain language'),
                        Select::make('policy.identity.model_tier')
                            ->label('Model tier')
                            ->options([
                                'default' => 'Default',
                                'fast' => 'Fast / low cost',
                                'reasoning' => 'Reasoning',
                            ])
                            ->default('default'),
                        TagsInput::make('policy.scope.owns')
                            ->label('Owns')
                            ->placeholder('Add a responsibility'),
                        TagsInput::make('policy.scope.does_not_own')
                            ->label('Does not own')
                            ->placeholder('Add an explicit non-goal'),
                        Textarea::make('policy.writes.notes')
                            ->label('Write notes')
                            ->rows(2)
                            ->columnSpanFull(),
                        Select::make('policy.data_access.student_pii')
                            ->label('Student PII handling')
                            ->options([
                                'minimize' => 'Minimize — use only what the task needs',
                                'no_pii' => 'No PII — aggregate or anonymized only',
                            ])
                            ->default('minimize'),
                    ]),

                Section::make('Tools and runtime')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('allowed_tools')
                            ->label('Allowed tools')
                            ->options(fn (Get $get): array => ToolRegistry::options(
                                $get('kind') !== AiBot::KIND_COORDINATOR,
                            ))
                            ->columns(2)
                            ->searchable()
                            ->bulkToggleable()
                            ->helperText('Write tools always stage an approval card. Coordinators cannot hold write tools.')
                            ->columnSpanFull(),
                        TextInput::make('model')
                            ->label('Model override')
                            ->maxLength(191)
                            ->helperText('Leave blank to use the workspace default provider and model.'),
                        TextInput::make('max_steps')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(8),
                        Select::make('memory_scope')
                            ->options([
                                AiBot::MEMORY_SESSION => 'Session only',
                                AiBot::MEMORY_BOT => 'Bot memory across sessions',
                            ])
                            ->default(AiBot::MEMORY_SESSION),
                        Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Inactive bots disappear from the picker and reject new handoffs.'),
                    ]),

                Section::make('Delegation and escalation')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('policy.delegation.mode')
                            ->label('Delegation mode')
                            ->options([
                                'none' => 'None',
                                'sync' => 'Sync — answer inside this turn',
                                'async' => 'Async — reply later',
                                'sync_and_async' => 'Sync and async',
                            ])
                            ->visible(fn (Get $get): bool => $get('kind') === AiBot::KIND_COORDINATOR),
                        TextInput::make('policy.delegation.max_depth')
                            ->label('Maximum depth')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(2)
                            ->default(2)
                            ->visible(fn (Get $get): bool => $get('kind') === AiBot::KIND_COORDINATOR),
                        TagsInput::make('policy.delegation.allowed_targets')
                            ->label('Allowed specialist slugs')
                            ->placeholder('e.g. registrar')
                            ->helperText('Leave empty to allow every active specialist in the workspace.')
                            ->visible(fn (Get $get): bool => $get('kind') === AiBot::KIND_COORDINATOR)
                            ->columnSpanFull(),
                        TagsInput::make('policy.escalation.triggers')
                            ->label('Escalation triggers')
                            ->placeholder('Add a trigger'),
                        TextInput::make('policy.escalation.to')
                            ->label('Escalate to')
                            ->default('chief')
                            ->visible(fn (Get $get): bool => $get('kind') !== AiBot::KIND_COORDINATOR),
                    ]),

                Section::make('Output contract')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('policy.output.format')
                            ->label('Format')
                            ->maxLength(255)
                            ->default('Outcome, facts, next step'),
                        TagsInput::make('policy.output.must_include')
                            ->label('Must include')
                            ->placeholder('Add a required element'),
                        TagsInput::make('policy.output.must_not')
                            ->label('Must never include')
                            ->placeholder('Add a forbidden element'),
                        TagsInput::make('policy.guardrails')
                            ->label('Guardrails')
                            ->placeholder('Add a guardrail')
                            ->columnSpanFull(),
                    ]),

                Section::make('Audit and health')
                    ->description('Read-only signals for this bot. Fix drift before changing the allowlist or policy.')
                    ->columnSpanFull()
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->schema([
                        Placeholder::make('drift_warnings')
                            ->label('Drift warnings')
                            ->content(function (?AiBot $record): string {
                                if (! $record) {
                                    return 'Save the bot to see drift warnings.';
                                }

                                $warnings = $record->driftWarnings();

                                return $warnings === []
                                    ? 'No drift detected.'
                                    : collect($warnings)->map(fn (string $warning): string => '• '.$warning)->implode("\n");
                            })
                            ->columnSpanFull(),
                        Placeholder::make('usage_summary')
                            ->label('Usage — last 30 days')
                            ->content(function (?AiBot $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $logs = $record->usageLogs()
                                    ->where('date', '>=', now()->subDays(30)->toDateString())
                                    ->get();

                                return $logs->isEmpty()
                                    ? 'No usage recorded.'
                                    : $logs->sum('input_tokens').' input tokens, '
                                        .$logs->sum('output_tokens').' output tokens, $'
                                        .number_format($logs->sum('estimated_cost_micros') / 1_000_000, 4).' estimated cost.';
                            }),
                        Placeholder::make('handoff_summary')
                            ->label('Handoffs')
                            ->content(function (?AiBot $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $counts = $record->handoffsFrom()
                                    ->selectRaw('status, count(*) as aggregate')
                                    ->groupBy('status')
                                    ->pluck('aggregate', 'status');

                                return $counts->isEmpty()
                                    ? 'No handoffs yet.'
                                    : $counts->map(fn ($count, string $status): string => "{$status}: {$count}")->implode(', ');
                            }),
                        Placeholder::make('routine_summary')
                            ->label('Routines')
                            ->content(function (?AiBot $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $routines = $record->routines()->orderBy('name')->get();

                                return $routines->isEmpty()
                                    ? 'No routines configured.'
                                    : $routines->map(fn ($routine): string => "{$routine->name} ({$routine->cron}) — "
                                        .($routine->last_status ?: 'never run'))->implode("\n");
                            }),
                        Placeholder::make('revision_summary')
                            ->label('Recent policy revisions')
                            ->content(function (?AiBot $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $revisions = $record->policyRevisions()->limit(5)->get();

                                return $revisions->isEmpty()
                                    ? 'No revisions recorded.'
                                    : $revisions->map(fn ($revision): string => 'v'.$revision->version.' — '
                                        .($revision->created_at?->diffForHumans() ?? 'unknown')
                                        .($revision->change_summary ? ': '.$revision->change_summary : ''))->implode("\n");
                            }),
                        Placeholder::make('memory_summary')
                            ->label('Durable memory')
                            ->content(function (?AiBot $record): string {
                                if (! $record) {
                                    return '—';
                                }

                                $memories = $record->memories()->latest('id')->limit(5)->get();

                                return $memories->isEmpty()
                                    ? 'No durable facts stored.'
                                    : $memories->map(fn ($memory): string => '• '.$memory->content)->implode("\n");
                            }),
                    ]),
            ]);
    }
}
