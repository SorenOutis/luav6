<?php

use App\Ai\Agents\AssistantAgent;
use App\Http\Data\AuthUserData;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

it('adds report-only CSP and baseline browser security headers', function () {
    config(['security.csp_mode' => 'report-only']);

    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

    $csp = $response->headers->get('Content-Security-Policy-Report-Only');
    expect($csp)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain('report-uri /csp/report')
        ->toMatch("/script-src 'self' 'nonce-[^']+'/");
});

it('can switch CSP from report-only to enforcement', function () {
    config(['security.csp_mode' => 'enforce']);

    $response = $this->get('/');

    expect($response->headers->has('Content-Security-Policy'))->toBeTrue()
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

it('adds HSTS to secure requests without opting into preload by default', function () {
    $response = $this->withServerVariables(['HTTPS' => 'on'])->get('/');

    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('accepts bounded CSP reports without CSRF and logs only structured fields', function () {
    Log::spy();

    $this->postJson(route('csp.report'), [
        'csp-report' => [
            'document-uri' => 'https://school.example/dashboard?secret=value',
            'violated-directive' => 'script-src-elem',
            'blocked-uri' => 'inline',
            'status-code' => 200,
        ],
    ])->assertNoContent();

    Log::shouldHaveReceived('notice')
        ->withArgs(fn (string $message, array $context): bool => $message === 'Content Security Policy violation'
            && $context['document_uri'] === 'https://school.example/dashboard'
            && $context['violated_directive'] === 'script-src-elem'
            && $context['blocked_uri'] === 'inline')
        ->once();
});

it('serializes an explicit minimal authenticated user DTO', function () {
    $user = User::factory()->create([
        'exp' => 999,
        'points' => 888,
        'level' => 7,
    ]);

    $dto = AuthUserData::from($user);

    expect($dto)->toHaveKeys([
        'id', 'public_id', 'name', 'first_name', 'middle_name', 'last_name',
        'email', 'avatar', 'cover_photo', 'bio', 'is_admin', 'is_super_admin',
        'is_banned', 'banned_at', 'ban_reason', 'profile_visibility',
        'profile_show_activity', 'profile_show_sections', 'profile_show_social',
        'profile_show_achievements', 'email_verified_at',
    ]);

    expect(array_intersect(array_keys($dto), [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'exp', 'points', 'level', 'current_workspace_id', 'created_at', 'updated_at',
    ]))->toBe([]);
});

it('rejects overlong and over-budget chat messages before prompting AI', function () {
    AssistantAgent::fake(['Never used']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('chat'), ['message' => str_repeat('a', 8001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    // Multibyte text can remain below the character limit while exceeding the
    // conservative request token estimate.
    $this->actingAs($user)
        ->postJson(route('chat'), ['message' => str_repeat('界', 4000)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');

    AssistantAgent::assertNeverPrompted();
});

it('enforces the synchronous widget attachment count on the server', function () {
    AssistantAgent::fake(['Never used']);
    $files = collect(range(1, 5))
        ->map(fn (int $number) => UploadedFile::fake()->create("document-{$number}.pdf", 1, 'application/pdf'))
        ->all();

    $this->actingAs(User::factory()->create())
        ->postJson(route('chat'), [
            'message' => 'Review these documents.',
            'attachments' => $files,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments');

    AssistantAgent::assertNeverPrompted();
});
