<?php

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Agents\AssistantAgent;
use App\Models\Exam;
use App\Models\PendingAiAction;
use App\Models\Setting;
use App\Models\User;
use App\Services\AiQuestionGeneratorService;
use App\Services\AiSdkProviderService;
use App\Services\AIService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiManager;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Tools\ToolNameResolver;

use function Laravel\Ai\agent;

beforeEach(function () {
    Setting::flushAllCaches();
});

function compatibleProvider(string $id = '1f1b5e48-58c5-4c83-94c9-cce63fb827d8'): array
{
    return [
        'id' => $id,
        'name' => 'Campus Gateway',
        'url' => 'https://gateway.example.test/v1',
        'model' => 'qwen3-32b',
        'api_key' => 'gateway-key',
        'headers' => [
            ['name' => 'X-Campus-ID', 'value' => 'north'],
            ['name' => 'Authorization', 'value' => 'ApiKey gateway-header-key'],
        ],
    ];
}

function storeCompatibleProvider(array $provider): string
{
    Setting::set('openai_compatible_providers', json_encode([$provider], JSON_THROW_ON_ERROR));

    return AiSdkProviderService::compatibleProviderNameForId($provider['id']);
}

it('discovers configured OpenAI-compatible providers and applies their runtime configuration', function () {
    $name = storeCompatibleProvider(compatibleProvider());

    expect(AiSdkProviderService::isSdkRouted($name))->toBeTrue()
        ->and(AiSdkProviderService::configuredProviders())->toHaveKey($name)
        ->and(AiSdkProviderService::for($name)->isConfigured())->toBeTrue();

    AiSdkProviderService::for($name)->applyToSdk();

    expect(config("ai.providers.{$name}"))->toMatchArray([
        'driver' => AiSdkProviderService::HEADER_AWARE_OPENAI_COMPATIBLE_DRIVER,
        'key' => 'gateway-key',
        'url' => 'https://gateway.example.test/v1',
        'headers' => [
            'X-Campus-ID' => 'north',
            'Authorization' => 'ApiKey gateway-header-key',
        ],
        'models' => ['text' => ['default' => 'qwen3-32b']],
    ]);

    expect(app(AiManager::class)->instance($name)->providerCredentials()['key'])->toBe('gateway-key');
});

it('sends compatible-provider bearer and custom headers through the SDK driver', function () {
    $provider = compatibleProvider();
    $provider['headers'] = [['name' => 'X-Campus-ID', 'value' => 'north']];
    $name = storeCompatibleProvider($provider);
    AiSdkProviderService::for($name)->applyToSdk();

    Http::fake([
        'gateway.example.test/*' => Http::response([
            'model' => 'qwen3-32b',
            'choices' => [[
                'message' => ['content' => 'Gateway response'],
                'finish_reason' => 'stop',
            ]],
        ]),
    ]);

    $response = agent(instructions: 'You are concise.')
        ->prompt('Hello', provider: $name, model: 'qwen3-32b');

    expect($response->text)->toBe('Gateway response');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://gateway.example.test/v1/chat/completions'
        && $request->hasHeader('X-Campus-ID', 'north')
        && $request->hasHeader('Authorization', 'Bearer gateway-key')
        && $request['model'] === 'qwen3-32b');
});

it('allows a custom authorization header to override the bearer token', function () {
    $name = storeCompatibleProvider(compatibleProvider());
    AiSdkProviderService::for($name)->applyToSdk();

    Http::fake([
        'gateway.example.test/*' => Http::response([
            'choices' => [['message' => ['content' => 'Gateway response']]],
        ]),
    ]);

    agent(instructions: 'You are concise.')
        ->prompt('Hello', provider: $name, model: 'qwen3-32b');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'ApiKey gateway-header-key'));
});

it('routes chat, grading, and question generation through the selected compatible provider', function () {
    $name = storeCompatibleProvider(compatibleProvider());
    Setting::set('ai_provider', $name);

    AssistantAgent::fake(['Campus chat']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('chat'), ['message' => 'Hello'])
        ->assertSuccessful()
        ->assertJsonPath('response', 'Campus chat');

    AssistantAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === $name
        && $prompt->model === 'qwen3-32b');

    AnonymousAgent::fake(['{"score": 80}', '{"questions": []}']);

    expect(app(AIService::class)->assessEssay('Essay', 'Question', 10)['score'])->toBe(8)
        ->and(app(AiQuestionGeneratorService::class)->generate('Source', ['multiple_choice' => 1]))->toBe([]);

    AnonymousAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === $name
        && $prompt->model === 'qwen3-32b');
});

it('streams chat through the selected compatible provider', function () {
    $name = storeCompatibleProvider(compatibleProvider());
    Setting::set('ai_provider', $name);

    AssistantAgent::fake(['Campus stream']);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('chat.stream'), ['message' => 'Hello']);

    $response->assertSuccessful();

    expect($response->streamedContent())->toContain('stream_start');

    AssistantAgent::assertPrompted(fn ($prompt) => $prompt->provider->name() === $name
        && $prompt->model === 'qwen3-32b');
});

it('exposes admin tool names matching the agent instructions', function () {
    $agent = new AdminAssistantAgent;
    $names = array_map(fn ($tool): string => ToolNameResolver::resolve($tool), $agent->tools());

    expect($names)->toBe([
        'workspace_overview', 'students', 'exams_admin', 'submissions_to_grade',
        'generate_exam_questions', 'create_exam', 'update_exam', 'post_announcement', 'create_assignment',
    ]);

    foreach ($names as $name) {
        expect((string) $agent->instructions())->toContain($name);
    }
});

it('executes admin tool calls through the compatible SDK without bypassing approval', function (bool $streaming, string $toolName) {
    $this->actingAs($admin = User::factory()->superAdmin()->create());
    $session = $admin->chatSessions()->create(['title' => 'Provider regression']);
    $name = storeCompatibleProvider(compatibleProvider());
    AiSdkProviderService::for($name)->applyToSdk();
    Http::preventStrayRequests();

    $arguments = $toolName === 'create_exam'
        ? ['title' => 'Biology midterm', 'exam_date' => '2026-10-01 09:00']
        : new stdClass;
    $toolCall = [
        'id' => 'call_admin_1',
        'type' => 'function',
        'function' => ['name' => $toolName, 'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR)],
    ];
    $sequence = Http::sequence();

    if ($streaming) {
        $toolCall['index'] = 0;
        $sequence->push(
            'data: '.json_encode(['choices' => [['index' => 0, 'delta' => ['role' => 'assistant', 'tool_calls' => [$toolCall]], 'finish_reason' => null]]])."\n\n"
            .'data: '.json_encode(['choices' => [['index' => 0, 'delta' => new stdClass, 'finish_reason' => 'tool_calls']]])."\n\ndata: [DONE]\n\n",
            200, ['Content-Type' => 'text/event-stream'],
        )->push(
            'data: '.json_encode(['choices' => [['index' => 0, 'delta' => ['content' => 'Review complete'], 'finish_reason' => null]]])."\n\n"
            .'data: '.json_encode(['choices' => [['index' => 0, 'delta' => new stdClass, 'finish_reason' => 'stop']]])."\n\ndata: [DONE]\n\n",
            200, ['Content-Type' => 'text/event-stream'],
        );
    } else {
        $sequence->push(['choices' => [[
            'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [$toolCall]],
            'finish_reason' => 'tool_calls',
        ]]])->push(['choices' => [[
            'message' => ['role' => 'assistant', 'content' => 'Review complete'],
            'finish_reason' => 'stop',
        ]]]);
    }

    Http::fake(['gateway.example.test/*' => $sequence]);
    $agent = (new AdminAssistantAgent)->setChatSessionId($session->id);

    if ($streaming) {
        $events = iterator_to_array($agent->stream('Manage my workspace', provider: $name, model: 'qwen3-32b'));
        expect($events)->not->toBeEmpty();
    } else {
        expect($agent->prompt('Manage my workspace', provider: $name, model: 'qwen3-32b')->text)->toBe('Review complete');
    }

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request) use ($toolName): bool {
        $result = collect($request['messages'])->firstWhere('role', 'tool');

        return in_array($toolName, array_column(array_column($request['tools'], 'function'), 'name'), true)
            && $result !== null
            && $result['tool_call_id'] === 'call_admin_1'
            && ($toolName === 'create_exam'
                ? str_contains($result['content'], 'PENDING HUMAN APPROVAL')
                : isset(json_decode($result['content'], true)['exams']));
    });

    expect(Exam::count())->toBe(0);
    if ($toolName === 'create_exam') {
        $action = PendingAiAction::sole();
        expect($action->status)->toBe('pending')
            ->and($action->chat_session_id)->toBe($session->id);
    } else {
        expect(PendingAiAction::count())->toBe(0);
    }
})->with([false, true])->with(['workspace_overview', 'create_exam']);

it('reports a removed compatible provider instead of falling back to another provider', function () {
    Setting::set('ai_provider', 'openai-compatible-1f1b5e48-58c5-4c83-94c9-cce63fb827d8');

    expect(fn () => app(AiQuestionGeneratorService::class)->generate('Source', ['multiple_choice' => 1]))
        ->toThrow('The selected OpenAI-compatible provider was removed.');
});
