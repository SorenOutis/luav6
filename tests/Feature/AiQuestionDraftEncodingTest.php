<?php

use App\Models\AiQuestionDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Create a draft row directly in the database, bypassing Eloquent events and
 * accessors — simulating legacy rows written before UTF-8 protection existed.
 */
function insertRawDraft(User $admin, array $overrides = []): int
{
    return (int) DB::table('ai_question_drafts')->insertGetId(array_merge([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'workspace_id' => $admin->current_workspace_id,
        'title' => 'Legacy draft',
        'source_filename' => 'chapter3.pdf',
        'source_text' => "Cell \xC3\x28 division \x93mitosis\x94\x00 and \xF9meiosis",
        'topic' => "Bio \xC3\x28 101",
        'type_counts' => json_encode(['multiple_choice' => 2]),
        'difficulty' => 'medium',
        'status' => 'ready',
        'questions' => json_encode([
            [
                'text' => 'What is mitosis?',
                'type' => 'multiple_choice',
                'points' => 1,
                'options' => [
                    ['text' => 'Cell division', 'is_correct' => true],
                    ['text' => 'Photosynthesis', 'is_correct' => false],
                ],
            ],
        ]),
        'last_error' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

test('drafts saved through the model are stored as valid UTF-8', function () {
    $admin = User::factory()->admin()->create();

    $draft = AiQuestionDraft::withoutGlobalScope('workspace')->create([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'title' => "Quiz \xC3\x28 draft",
        'source_text' => "Extracted \x93PDF\x94 text\x00\x01 with bad bytes \xC3\x28",
        'type_counts' => ['multiple_choice' => 2],
        'status' => 'pending',
    ]);

    $raw = DB::table('ai_question_drafts')->where('id', $draft->id)->first();

    expect(mb_check_encoding($raw->source_text, 'UTF-8'))->toBeTrue();
    expect(mb_check_encoding($raw->title, 'UTF-8'))->toBeTrue();
    expect(json_encode((array) $raw, JSON_THROW_ON_ERROR))->toBeString();
});

test('oversized source text is capped on save', function () {
    $admin = User::factory()->admin()->create();

    $draft = AiQuestionDraft::withoutGlobalScope('workspace')->create([
        'user_id' => $admin->id,
        'admin_id' => $admin->id,
        'title' => 'Huge source',
        'source_text' => str_repeat('a', AiQuestionDraft::MAX_SOURCE_TEXT_LENGTH + 5000),
        'type_counts' => ['multiple_choice' => 1],
        'status' => 'pending',
    ]);

    expect(mb_strlen($draft->fresh()->source_text))->toBe(AiQuestionDraft::MAX_SOURCE_TEXT_LENGTH);
});

test('re-saving a legacy corrupted draft repairs it', function () {
    $admin = User::factory()->admin()->create();
    $id = insertRawDraft($admin);

    $rawBefore = DB::table('ai_question_drafts')->where('id', $id)->first();
    expect(mb_check_encoding($rawBefore->source_text, 'UTF-8'))->toBeFalse();

    $draft = AiQuestionDraft::withoutGlobalScope('workspace')->findOrFail($id);
    $draft->save();

    $rawAfter = DB::table('ai_question_drafts')->where('id', $id)->first();
    expect(mb_check_encoding($rawAfter->source_text, 'UTF-8'))->toBeTrue();
    expect(mb_check_encoding($rawAfter->topic, 'UTF-8'))->toBeTrue();
});

test('the edit page renders even for a legacy row with malformed UTF-8', function () {
    $admin = User::factory()->admin()->create();
    $id = insertRawDraft($admin);

    $this->actingAs($admin)
        ->get("/admin/ai-question-drafts/{$id}/edit")
        ->assertOk();
});
