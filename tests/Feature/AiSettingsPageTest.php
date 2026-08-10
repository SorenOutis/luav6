<?php

/**
 * Platform Settings AI provider page tests.
 *
 * The AI Provider Configuration section shows every Laravel AI SDK provider
 * as its own collapsible card instead of a select. Exactly one card is
 * checked as the default provider (radio semantics) and saving persists the
 * default key plus every provider's credentials to the Setting model.
 */

use App\Filament\Pages\AiSettings;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Setting::flushAllCaches();
});

it('restricts access to super admins', function () {
    $this->actingAs(User::factory()->admin()->create());
    expect(AiSettings::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->superAdmin()->create());
    expect(AiSettings::canAccess())->toBeTrue();
});

it('shows a card with a default checkbox for every text-capable provider', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(AiSettings::class)
        ->assertSchemaComponentExists('ai_provider', 'form')
        ->assertSchemaComponentExists('provider_default_gemini', 'form')
        ->assertSchemaComponentExists('provider_default_openai', 'form')
        ->assertSchemaComponentExists('provider_default_anthropic', 'form')
        ->assertSchemaComponentExists('provider_default_groq', 'form')
        ->assertSchemaComponentExists('provider_default_mistral', 'form')
        ->assertSchemaComponentExists('provider_default_deepseek', 'form')
        ->assertSchemaComponentExists('provider_default_xai', 'form')
        ->assertSchemaComponentExists('provider_default_openrouter', 'form')
        ->assertSchemaComponentExists('provider_default_azure', 'form')
        ->assertSchemaComponentExists('provider_default_ollama', 'form')
        ->assertSchemaComponentExists('provider_default_cloudflare', 'form')
        ->assertSchemaComponentExists('openai_api_key', 'form')
        ->assertSchemaComponentExists('openai_model', 'form')
        ->assertSchemaComponentExists('anthropic_api_key', 'form')
        ->assertSchemaComponentExists('azure_deployment', 'form')
        ->assertSchemaComponentExists('cohere_api_key', 'form')
        ->assertSchemaComponentExists('jina_api_key', 'form')
        ->assertSchemaComponentExists('voyageai_api_key', 'form')
        ->assertSchemaComponentExists('eleven_api_key', 'form');
});

it('checks the stored default provider on mount', function () {
    Setting::set('ai_provider', 'openai');

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(AiSettings::class)
        ->assertSet('data.ai_provider', 'openai')
        ->assertSet('data.provider_default_openai', true)
        ->assertSet('data.provider_default_gemini', false);
});

it('moves the default check mark between providers like a radio button', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(AiSettings::class)
        ->assertSet('data.ai_provider', 'gemini')
        ->assertSet('data.provider_default_gemini', true)
        ->set('data.provider_default_openai', true)
        ->assertSet('data.ai_provider', 'openai')
        ->assertSet('data.provider_default_gemini', false)
        // Unchecking the current default snaps back on — exactly one default.
        ->set('data.provider_default_openai', false)
        ->assertSet('data.provider_default_openai', true)
        ->assertSet('data.ai_provider', 'openai');
});

it('persists the default provider and every provider card on save', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(AiSettings::class)
        ->set('data.provider_default_openai', true)
        ->set('data.openai_api_key', 'sk-test-key')
        ->set('data.openai_model', 'gpt-4o')
        ->set('data.anthropic_api_key', 'claude-key')
        ->set('data.cohere_api_key', 'cohere-key')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('ai_provider'))->toBe('openai')
        ->and(Setting::get('openai_api_key'))->toBe('sk-test-key')
        ->and(Setting::get('openai_model'))->toBe('gpt-4o')
        ->and(Setting::get('anthropic_api_key'))->toBe('claude-key')
        ->and(Setting::get('cohere_api_key'))->toBe('cohere-key');
});

it('falls back to gemini when the stored default can never serve text', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(AiSettings::class)
        ->set('data.ai_provider', 'cohere')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('ai_provider'))->toBe('gemini');
});
