import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = (path: string) =>
    readFileSync(join(process.cwd(), path), 'utf8');

describe('workspace AI budget interface', () => {
    it('exposes token cost warning and fallback controls', () => {
        const settings = source('app/Filament/Pages/AiSettings.php');

        expect(settings).toContain("TextInput::make('ai_budget_daily_tokens')");
        expect(settings).toContain(
            "TextInput::make('ai_budget_monthly_tokens')",
        );
        expect(settings).toContain("TextInput::make('ai_budget_daily_cost')");
        expect(settings).toContain("TextInput::make('ai_budget_monthly_cost')");
        expect(settings).toContain("Select::make('ai_fallback_mode')");
        expect(settings).toContain("Select::make('ai_fallback_provider')");
        expect(settings).toContain("Repeater::make('ai_budget_cost_rates')");
    });

    it('renders feature provider workspace and event breakdowns', () => {
        const dashboard = source(
            'resources/views/filament/pages/ai-usage-dashboard.blade.php',
        );

        expect(dashboard).toContain('Usage by feature this month');
        expect(dashboard).toContain('Usage by provider and model');
        expect(dashboard).toContain('Workspace usage this month');
        expect(dashboard).toContain('Recent budget events');
        expect(dashboard).toContain('Tokens committed');
        expect(dashboard).toContain('Estimated cost committed');
    });
});
