<?php

use App\Models\Section;
use App\Models\User;

function createSection(array $overrides = []): Section
{
    return Section::create(array_merge([
        'name' => fake()->unique()->word().' Section',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'ABCD1234',
    ], $overrides));
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('unauthenticated user cannot join a section', function () {
    createSection();

    $this->postJson('/sections/join-by-code', ['code' => 'ABCD1234'])
        ->assertUnauthorized();
});

test('user can join a section with a valid code', function () {
    $section = createSection();

    $this->actingAs($this->user)
        ->postJson('/sections/join-by-code', ['code' => 'ABCD1234'])
        ->assertOk()
        ->assertJson([
            'valid' => true,
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'already_joined' => false,
            ],
        ]);

    expect($this->user->sections()->where('section_id', $section->id)->exists())->toBeTrue();
});

test('user gets already_joined when re-joining a section', function () {
    $section = createSection();
    $this->user->sections()->syncWithoutDetaching([$section->id]);

    $this->actingAs($this->user)
        ->postJson('/sections/join-by-code', ['code' => 'ABCD1234'])
        ->assertOk()
        ->assertJson([
            'valid' => true,
            'section' => [
                'id' => $section->id,
                'already_joined' => true,
            ],
        ]);
});

test('invalid code returns error', function () {
    createSection();

    $this->actingAs($this->user)
        ->postJson('/sections/join-by-code', ['code' => 'ZZZZZZZZ'])
        ->assertStatus(422)
        ->assertJson([
            'valid' => false,
            'message' => 'Invalid section code. Please check and try again.',
        ]);
});

test('code with hyphen is normalized correctly', function () {
    $section = createSection();

    $this->actingAs($this->user)
        ->postJson('/sections/join-by-code', ['code' => 'ABCD-1234'])
        ->assertOk()
        ->assertJsonPath('section.id', $section->id);
});

test('code is case-insensitive', function () {
    $section = createSection();

    $this->actingAs($this->user)
        ->postJson('/sections/join-by-code', ['code' => 'abcd1234'])
        ->assertOk()
        ->assertJsonPath('section.id', $section->id);
});

test('section with missing join_code cannot be joined by code', function () {
    createSection(['join_code' => null]);

    $this->actingAs($this->user)
        ->postJson('/sections/join-by-code', ['code' => 'ABCD1234'])
        ->assertStatus(422)
        ->assertJson(['valid' => false]);
});

test('code validation rejects empty or too-long strings', function () {
    $this->actingAs($this->user)
        ->postJson('/sections/join-by-code', ['code' => ''])
        ->assertStatus(422);

    $this->actingAs($this->user)
        ->postJson('/sections/join-by-code', ['code' => 'ABCDEFGHIJ']) // 10 chars
        ->assertStatus(422);
});
