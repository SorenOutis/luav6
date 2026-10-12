<?php

namespace App\Filament\Pages;

use App\Jobs\DeliverAiDailyReport;
use App\Models\AiDailyReport;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiDailyReportAnalystService;
use App\Services\AiDailyReportService;
use App\Services\AiDailyReportSnapshotService;
use App\Services\AiSdkProviderService;
use App\Support\AiDailyReportSettings;
use App\Support\WorkspaceContext;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AiDailyReports extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'AI Daily Reports';

    protected string $view = 'filament.pages.ai-daily-reports';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && User::query()
            ->whereKey($user->getAuthIdentifier())
            ->where('is_admin', true)
            ->where('is_super_admin', true)
            ->exists();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = AiDailyReportSettings::state();
        $hasSavedSettings = app(WorkspaceContext::class)->run(null, fn (): bool => Setting::get(AiDailyReportSettings::SETTINGS_KEY) !== null);

        if (! $hasSavedSettings) {
            $state['recipient_id'] = (int) Filament::auth()->id();
        }

        $this->form->fill($state);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Nightly AI report settings')
                    ->description('Platform-wide settings, independent of the workspace currently being inspected. Reports stay disabled until you configure and enable them.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Enable nightly AI reports')
                            ->live()
                            ->columnSpanFull(),

                        TimePicker::make('time')
                            ->label('Daily report time')
                            ->seconds(false)
                            ->format('H:i')
                            ->required()
                            ->helperText('24-hour local time ('.config('app.timezone').') when generation begins. Each report covers the previous complete calendar day.')
                            ->columnSpan(1),

                        TextInput::make('max_cost_usd')
                            ->label('Estimated cost cap per report (USD)')
                            ->numeric()
                            ->minValue(0.001)
                            ->maxValue(10)
                            ->step(0.001)
                            ->required()
                            ->helperText('Estimated AI cost limit for one complete report, not a workspace budget. Actual provider billing may differ.')
                            ->columnSpan(1),

                        Select::make('workspace_ids')
                            ->label('Workspaces')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Workspace::query()
                                ->where('name', 'like', "%{$search}%")
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'id')
                                ->all())
                            ->getOptionLabelsUsing(fn (array $values): array => Workspace::query()
                                ->whereIn('id', $values)
                                ->pluck('name', 'id')
                                ->all())
                            ->maxItems(25)
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->helperText('Choose up to 25 workspaces. Archived workspaces can also be selected.')
                            ->columnSpanFull(),

                        Select::make('recipient_id')
                            ->label('Email recipient')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->recipientQuery()
                                ->where(fn (Builder $query): Builder => $query
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%"))
                                ->orderBy('email')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} ({$user->email})"])
                                ->all())
                            ->getOptionLabelUsing(function (mixed $value): ?string {
                                $user = $this->recipientQuery()->find($value);

                                return $user ? "{$user->name} ({$user->email})" : null;
                            })
                            ->required(fn (Get $get): bool => (bool) $get('enabled'))
                            ->helperText('Super admins who will receive the daily report.')
                            ->columnSpan(1),

                        Select::make('provider')
                            ->label('AI provider')
                            ->options(fn (): array => AiDailyReportSettings::providerOptions())
                            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->supportedProviderLabels()[$value] ?? null)
                            ->in(fn (Get $get): array => array_keys($get('enabled')
                                ? AiDailyReportSettings::providerOptions()
                                : $this->supportedProviderLabels()))
                            ->native(false)
                            ->required()
                            ->helperText('Only globally configured providers or local Ollama are available. Configure credentials in Platform Settings. Groq and Cloudflare are not supported for daily reports in v1.')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        try {
            AiDailyReportSettings::save($data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => ["data.{$key}" => $messages])
                ->all());
        }

        Notification::make()
            ->title('Daily report settings saved')
            ->success()
            ->send();
    }

    public function rerunAndSend(int $reportId): void
    {
        abort_unless(static::canAccess(), 403);

        $report = AiDailyReport::query()->findOrFail($reportId);

        $report->update([
            'status' => AiDailyReport::STATUS_PENDING,
            'started_at' => null,
            'completed_at' => null,
            'delivery_started_at' => null,
            'delivery_failed_at' => null,
            'delivery_error_code' => null,
            'email_sent_at' => null,
        ]);

        try {
            app(AiDailyReportService::class)->generate(
                $report->id,
                app(AiDailyReportSnapshotService::class),
                app(AiDailyReportAnalystService::class),
            );

            (new DeliverAiDailyReport($report->id))->handle(app(AiDailyReportService::class));

            $report->refresh();

            if ($report->status === AiDailyReport::STATUS_COMPLETED && $report->email_sent_at !== null) {
                Notification::make()
                    ->title('Report re-analyzed and delivered!')
                    ->body("Delivered to {$report->recipient?->email}")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Report processed with notices')
                    ->body("Status: {$report->status}".($report->error_code ? " ({$report->error_code})" : ''))
                    ->warning()
                    ->send();
            }
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Failed to resend report')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return Collection<int, AiDailyReport>
     */
    public function getRecentReports(): Collection
    {
        abort_unless(static::canAccess(), 403);

        return app(WorkspaceContext::class)->run(null, fn (): Collection => AiDailyReport::query()
            ->select([
                'id',
                'report_date',
                'timezone',
                'status',
                'provider',
                'model',
                'error_code',
                'email_sent_at',
                'delivery_failed_at',
                'delivery_error_code',
                'analysis',
                'snapshot',
                'input_tokens',
                'output_tokens',
                'estimated_cost_micros',
            ])
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get());
    }

    protected function getViewData(): array
    {
        return [
            'reportTime' => AiDailyReportSettings::state()['time'],
            'reportTimezone' => config('app.timezone'),
            'recentReports' => $this->getRecentReports(),
        ];
    }

    /**
     * @return Builder<User>
     */
    protected function recipientQuery(): Builder
    {
        return User::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('is_super_admin', true)
                ->orWhere(fn (Builder $q) => $q->where('is_admin', true)->where('is_super_admin', true))
            );
    }

    /**
     * @return array<string, string>
     */
    protected function supportedProviderLabels(): array
    {
        return app(WorkspaceContext::class)->run(null, fn (): array => array_diff_key(
            AiSdkProviderService::textProviderLabels(),
            array_flip(['groq', 'cloudflare']),
        ));
    }
}
