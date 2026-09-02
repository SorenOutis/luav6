<?php

use App\Models\LearningMaterial;
use App\Models\LearningMaterialCategory;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->season = Season::factory()->active()->create();
    $this->sectionA = Section::factory()->forSeason($this->season)->create(['name' => 'Alpha']);
    $this->sectionB = Section::factory()->forSeason($this->season)->create(['name' => 'Beta']);
    $this->category = LearningMaterialCategory::create(['name' => 'Reviewers '.rand(1000, 9999), 'slug' => 'reviewers-'.rand(1000, 9999), 'workspace_id' => $this->sectionA->workspace_id]);
});

function makeMaterial(array $sectionIds, array $attributes = []): LearningMaterial
{
    $material = LearningMaterial::create(array_merge([
        'title' => 'Untitled material',
        'description' => 'Desc',
        'learning_material_category_id' => test()->category->id,
        'file_path' => 'library/test.pdf',
        'file_name' => 'test.pdf',
        'file_size' => 12345,
        'mime_type' => 'application/pdf',
        'file_extension' => 'pdf',
        'status' => 'published',
        'is_downloadable' => true,
    ], $attributes));

    if (! empty($sectionIds)) {
        $material->sections()->sync($sectionIds);
    }

    return $material->fresh(['category', 'sections']);
}

it('shows a material only to students in a targeted section', function () {
    Storage::fake('public');
    Storage::disk('public')->put('library/test.pdf', '%PDF-1.4 fake');

    makeMaterial([$this->sectionA->id], ['title' => 'Alpha only']);
    makeMaterial([$this->sectionB->id], ['title' => 'Beta only']);

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $this->actingAs($student)
        ->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Index')
            ->has('materials', 1)
            ->where('materials.0.title', 'Alpha only'));
});

it('hides unassigned material from everyone', function () {
    Storage::fake('public');
    makeMaterial([], ['title' => 'No sections']);

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $this->actingAs($student)
        ->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('materials', 0));
});

it('hides draft materials from students but admin can see via isVisibleTo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('library/test.pdf', '%PDF-1.4 fake');

    $draft = makeMaterial([$this->sectionA->id], ['title' => 'Draft', 'status' => 'draft']);
    $published = makeMaterial([$this->sectionA->id], ['title' => 'Published', 'status' => 'published']);

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);
    $admin = User::factory()->create(['is_admin' => true]);

    expect($draft->isVisibleTo($student))->toBeFalse();
    expect($draft->isVisibleTo($admin))->toBeTrue();
    expect($published->isVisibleTo($student))->toBeTrue();

    $this->actingAs($student)
        ->get(route('library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('materials', 1)
            ->where('materials.0.title', 'Published'));
});

it('blocks download when is_downloadable is false but allows preview', function () {
    Storage::fake('public');
    Storage::disk('public')->put('library/test.pdf', '%PDF-1.4 fake');

    $material = makeMaterial([$this->sectionA->id], ['title' => 'View only', 'is_downloadable' => false]);

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    // Preview (inline) should succeed
    $this->actingAs($student)
        ->get(route('library.file', $material).'?download=0')
        ->assertOk();

    // Download should be blocked
    $this->actingAs($student)
        ->get(route('library.file', $material).'?download=1')
        ->assertForbidden();

    // Check counts
    expect($material->fresh()->view_count)->toBe(1);
    expect($material->fresh()->download_count)->toBe(0);
});

it('allows download when is_downloadable is true', function () {
    Storage::fake('public');
    Storage::disk('public')->put('library/test.pdf', '%PDF-1.4 fake');

    $material = makeMaterial([$this->sectionA->id], ['title' => 'Downloadable', 'is_downloadable' => true]);

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $response = $this->actingAs($student)
        ->get(route('library.file', $material).'?download=1');
    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');

    expect($material->fresh()->download_count)->toBe(1);
});

it('blocks file access for student in other section', function () {
    Storage::fake('public');
    Storage::disk('public')->put('library/test.pdf', '%PDF-1.4 fake');

    $material = makeMaterial([$this->sectionB->id], ['title' => 'Beta only']);

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $this->actingAs($student)
        ->get(route('library.file', $material))
        ->assertForbidden();
});

it('sanitizes file names on download', function () {
    Storage::fake('public');
    Storage::disk('public')->put('library/test.pdf', '%PDF-1.4 fake');

    $material = makeMaterial([$this->sectionA->id], ['file_name' => '../../etc/passwd.pdf']);

    $student = User::factory()->create();
    $student->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $response = $this->actingAs($student)->get(route('library.file', $material));
    $response->assertOk();
    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->not->toContain('..');
    expect($disposition)->not->toContain('/');
});
