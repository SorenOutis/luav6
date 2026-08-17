<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Platform-wide setting keys managed from Platform Settings (AiSettings)
     * and Student Page Controls.
     *
     * These keys are consumed from contexts that never see an admin's
     * workspace scope: public registration/login pages run unauthenticated
     * (global scope only), and students read their own workspace scope with a
     * global fallback. The 2026_08_16 tenant migration scoped every
     * Setting::set() write to the admin's current workspace, so values saved
     * from Platform Settings silently did nothing everywhere else.
     *
     * This migration consolidates any workspace-scoped rows back into the
     * global scope — newest row wins, stale shadow rows are deleted so they
     * can never mask the global value again.
     *
     * Excluded on purpose: the "Workspace AI Budget & Fallback" keys
     * (ai_budget_*, ai_fallback_*, ai_budget_cost_rates, ollama_enabled) stay
     * tenant-scoped by design.
     */
    private array $platformKeys = [
        // Access control
        'login_enabled',
        'login_disabled_message',
        'registration_enabled',
        'registration_disabled_message',
        // AI chat + provider configuration
        'ai_chat_enabled',
        'ai_chat_maintenance_message',
        'ai_provider',
        'openai_compatible_providers',
        'gemini_api_key',
        'gemini_chat_model',
        'gemini_grading_model',
        'cloudflare_account_id',
        'cloudflare_api_token',
        'cloudflare_model',
        'cloudflare_grading_model',
        'groq_api_key',
        'groq_model',
        'ollama_url',
        'ollama_model',
        'openai_api_key',
        'openai_url',
        'openai_model',
        'anthropic_api_key',
        'anthropic_url',
        'anthropic_model',
        'mistral_api_key',
        'mistral_url',
        'mistral_model',
        'deepseek_api_key',
        'deepseek_model',
        'xai_api_key',
        'xai_url',
        'xai_model',
        'openrouter_api_key',
        'openrouter_model',
        'azure_api_key',
        'azure_url',
        'azure_api_version',
        'azure_deployment',
        'azure_embedding_deployment',
        'cohere_api_key',
        'jina_api_key',
        'voyageai_api_key',
        'eleven_api_key',
        // Daily / bonus XP claim
        'daily_claim_enabled',
        'daily_claim_base_xp',
        'daily_claim_bonus_enabled',
        'daily_claim_bonus_xp',
        // Branding + welcome
        'welcome_demo_video_path',
        'school_name',
        'school_tagline',
        'school_logo_path',
        'school_accent_color',
        // Student page controls
        'student_page_controls',
    ];

    public function up(): void
    {
        foreach ($this->platformKeys as $key) {
            $global = DB::table('settings')
                ->where('key', $key)
                ->whereNull('workspace_id')
                ->orderByDesc('id')
                ->first();

            $scoped = DB::table('settings')
                ->where('key', $key)
                ->whereNotNull('workspace_id')
                ->orderByDesc('id')
                ->get();

            if ($scoped->isEmpty()) {
                continue;
            }

            if ($global) {
                // A global row already exists — it is the platform value.
                // Workspace rows only shadow it for users inside that
                // workspace, so drop them.
                DB::table('settings')->whereIn('id', $scoped->pluck('id'))->delete();

                continue;
            }

            // Promote the newest workspace value to the global scope, then
            // delete the remaining stale workspace rows.
            DB::table('settings')->where('id', $scoped->first()->id)->update(['workspace_id' => null]);
            DB::table('settings')
                ->where('key', $key)
                ->whereNotNull('workspace_id')
                ->delete();
        }

        Setting::flushAllCaches();
    }

    public function down(): void
    {
        // Data consolidation is intentionally one-way: the promoted rows and
        // the global-write behavior in Setting::setGlobal() keep future saves
        // correct, and re-scattering values across workspaces would recreate
        // the bug this migration fixes.
    }
};
