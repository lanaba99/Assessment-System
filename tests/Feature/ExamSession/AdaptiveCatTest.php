<?php

declare(strict_types=1);

use App\Domains\ExamEngine\Contracts\QuestionSelectionService;
use App\Domains\ExamEngine\Enums\ExamStatus;
use App\Domains\ExamSession\Contracts\ExamSessionService;
use App\Domains\ExamSession\Exceptions\EnrollmentNotFoundException;
use App\Domains\ExamSession\Models\ExamSessionItem;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\ExamSession\UsesAdaptiveCatSchema;

uses(UsesAdaptiveCatSchema::class);

beforeEach(function (): void {
    $this->bootAdaptiveCatSchema();
    $this->withoutTenancyIdentificationMiddleware();
    $this->initializeTenantContext($this->tenantA);

    Event::fake([
        \App\Domains\ExamSession\Events\ExamSessionCompleted::class,
        \App\Domains\ExamSession\Events\ResponseSubmitted::class,
    ]);
});

/*
|--------------------------------------------------------------------------
| Shared fixture builder
|--------------------------------------------------------------------------
|
| Two-section adaptive exam:
|   Section 1 → competency A, max 2 items, pool of 3 calibrated mcq items.
|   Section 2 → competency B, max 1 item,  pool of 1 calibrated mcq item.
| Every version's correct answer is ['B'].
*/
function setUpTwoSectionAdaptiveExam(): array
{
    /** @var \Tests\TestCase $this */
    $admin = $this->createUser($this->tenantA, overrides: ['user_type' => 'admin']);
    $candidate = $this->createUser($this->tenantA);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
        'is_adaptive_exam' => true,
    ]);

    $section1 = $this->createExamSection((string) $exam->exam_id, $this->tenantA, ['section_sequence' => 1]);
    $section2 = $this->createExamSection((string) $exam->exam_id, $this->tenantA, ['section_sequence' => 2]);

    $category = $this->createCategoryForCat($this->tenantA);
    $competencyA = $this->createCompetencyForCat($this->tenantA, (string) $admin->id);
    $competencyB = $this->createCompetencyForCat($this->tenantA, (string) $admin->id);

    $this->createAdaptiveBlueprint((string) $exam->exam_id, (string) $section1->section_id, $competencyA, [
        'max_questions_count' => 2,
    ]);
    $this->createAdaptiveBlueprint((string) $exam->exam_id, (string) $section2->section_id, $competencyB, [
        'max_questions_count' => 1,
    ]);

    $section1Versions = [];
    for ($i = 0; $i < 3; $i++) {
        $section1Versions[] = $this->createCalibratedVersion(
            $this->tenantA, $category, (string) $admin->id, $competencyA,
        );
    }

    $section2Versions = [
        $this->createCalibratedVersion($this->tenantA, $category, (string) $admin->id, $competencyB),
    ];

    $enrollment = $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    return compact('admin', 'candidate', 'exam', 'section1', 'section2', 'competencyA', 'competencyB', 'section1Versions', 'section2Versions', 'enrollment');
}

function startAdaptive(array $ctx): Illuminate\Testing\TestResponse
{
    /** @var \Tests\TestCase $this */
    $this->grantPermissionsToUser($ctx['candidate'], ['exam_sessions.start']);
    Sanctum::actingAs($ctx['candidate']);

    return $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $ctx['exam']->exam_id])
        ->assertCreated();
}

function submitCorrect(string $sessionId, string $sessionItemId): Illuminate\Testing\TestResponse
{
    /** @var \Tests\TestCase $this */
    return $this->postJson('/api/v1/exam-sessions/' . $sessionId . '/responses', [
        'session_item_id' => $sessionItemId,
        'response_type' => 'mcq',
        'selected_options' => ['B'],
    ]);
}

/*
|--------------------------------------------------------------------------
| 1. First item — section 1 only
|--------------------------------------------------------------------------
*/
it('creates exactly one pending item in section 1, never section 2, when an adaptive session starts', function (): void {
    $ctx = setUpTwoSectionAdaptiveExam();

    $start = startAdaptive($ctx);

    $start->assertJsonPath('data.current.section_id', (string) $ctx['section1']->section_id)
        ->assertJsonPath('data.current.question_index', 1);

    $sessionId = $start->json('data.session_id');

    expect(ExamSessionItem::query()->where('session_id', $sessionId)->count())->toBe(1);

    $cat = $start->json('data.progress.progress_data.cat');
    expect($cat['status'])->toBe('in_progress');
    expect($cat['current_section_id'])->toBe((string) $ctx['section1']->section_id);
    expect($cat['sections'][(string) $ctx['section1']->section_id]['status'])->toBe('in_progress');
    expect($cat['sections'][(string) $ctx['section2']->section_id]['status'])->toBe('pending');
});

/*
|--------------------------------------------------------------------------
| 2. Section continues while its own stop condition is not met
|--------------------------------------------------------------------------
*/
it('keeps delivering items inside section 1 while its stop condition is not yet met', function (): void {
    $ctx = setUpTwoSectionAdaptiveExam();

    $start = startAdaptive($ctx);
    $sessionId = $start->json('data.session_id');
    $itemId = $start->json('data.current.session_item_id');

    $response = submitCorrect($sessionId, $itemId);

    $response->assertOk()
        ->assertJsonPath('data.current.section_id', (string) $ctx['section1']->section_id)
        ->assertJsonPath('data.current.question_index', 2);

    $cat = $response->json('data.progress.progress_data.cat');
    expect($cat['sections'][(string) $ctx['section1']->section_id]['items_administered_count'])->toBe(1);
    expect($cat['sections'][(string) $ctx['section1']->section_id]['status'])->toBe('in_progress');
});

/*
|--------------------------------------------------------------------------
| 3. Section 1 → section 2 handoff
|--------------------------------------------------------------------------
*/
it('moves to section 2, using the global item count as the next sequence number, once section 1 stops', function (): void {
    $ctx = setUpTwoSectionAdaptiveExam();

    $start = startAdaptive($ctx);
    $sessionId = $start->json('data.session_id');

    $item1 = $start->json('data.current.session_item_id');
    $r2 = submitCorrect($sessionId, $item1)->assertOk();

    $item2 = $r2->json('data.current.session_item_id');
    // This is item #2 of section 1 (max_items=2) — it should hit the stop
    // condition and hand off to section 2.
    $r3 = submitCorrect($sessionId, $item2)->assertOk();

    $r3->assertJsonPath('data.current.section_id', (string) $ctx['section2']->section_id)
        // Sequence numbers are GLOBAL per session (unique(['session_id',
        // 'sequence_number'])) — this must be 3, not restart at 1.
        ->assertJsonPath('data.current.question_index', 3);

    $cat = $r3->json('data.progress.progress_data.cat');
    expect($cat['current_section_id'])->toBe((string) $ctx['section2']->section_id);
    expect($cat['sections'][(string) $ctx['section1']->section_id]['status'])->toBe('completed');
    expect($cat['sections'][(string) $ctx['section1']->section_id]['stop_reason'])->toBe('max_items_reached');
    expect($cat['sections'][(string) $ctx['section2']->section_id]['status'])->toBe('in_progress');

    // No item ever repeats within section 1's own two picks.
    $section1ItemVersionIds = ExamSessionItem::query()
        ->where('session_id', $sessionId)
        ->where('section_id', (string) $ctx['section1']->section_id)
        ->pluck('question_version_id')
        ->all();
    expect($section1ItemVersionIds)->toHaveCount(2);
    expect(count(array_unique($section1ItemVersionIds)))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 4. No repeats across sections, even with overlapping competency pools
|--------------------------------------------------------------------------
*/
it('never repeats the same question version across sections, even when they target the same competency', function (): void {
    $admin = $this->createUser($this->tenantA, overrides: ['user_type' => 'admin']);
    $candidate = $this->createUser($this->tenantA);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
        'is_adaptive_exam' => true,
    ]);

    $section1 = $this->createExamSection((string) $exam->exam_id, $this->tenantA, ['section_sequence' => 1]);
    $section2 = $this->createExamSection((string) $exam->exam_id, $this->tenantA, ['section_sequence' => 2]);

    $category = $this->createCategoryForCat($this->tenantA);
    $sharedCompetency = $this->createCompetencyForCat($this->tenantA, (string) $admin->id);

    // BOTH sections target the SAME competency — a shared, exactly-2-item pool.
    $this->createAdaptiveBlueprint((string) $exam->exam_id, (string) $section1->section_id, $sharedCompetency, [
        'max_questions_count' => 1,
    ]);
    $this->createAdaptiveBlueprint((string) $exam->exam_id, (string) $section2->section_id, $sharedCompetency, [
        'max_questions_count' => 1,
    ]);

    $versionX = $this->createCalibratedVersion($this->tenantA, $category, (string) $admin->id, $sharedCompetency);
    $versionY = $this->createCalibratedVersion($this->tenantA, $category, (string) $admin->id, $sharedCompetency);

    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    $start = $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])->assertCreated();
    $sessionId = $start->json('data.session_id');
    $item1Id = $start->json('data.current.session_item_id');
    $item1VersionId = $start->json('data.current.question_version_id');

    $r2 = submitCorrect($sessionId, $item1Id)->assertOk();
    $item2VersionId = $r2->json('data.current.question_version_id');

    expect($item2VersionId)->not->toBeNull();
    expect($item2VersionId)->not->toBe($item1VersionId);
    expect([$item1VersionId, $item2VersionId])->toEqualCanonicalizing([$versionX, $versionY]);
});

/*
|--------------------------------------------------------------------------
| 5. Completion after the final section
|--------------------------------------------------------------------------
*/
it('marks the CAT run completed and returns no next item once the final section finishes', function (): void {
    $ctx = setUpTwoSectionAdaptiveExam();

    $start = startAdaptive($ctx);
    $sessionId = $start->json('data.session_id');

    $item1 = $start->json('data.current.session_item_id');
    $r2 = submitCorrect($sessionId, $item1)->assertOk();

    $item2 = $r2->json('data.current.session_item_id');
    $r3 = submitCorrect($sessionId, $item2)->assertOk(); // hands off to section 2

    $item3 = $r3->json('data.current.session_item_id');
    $r4 = submitCorrect($sessionId, $item3)->assertOk(); // section 2's only item

    $r4->assertJsonPath('data.current.session_item_id', null)
        ->assertJsonPath('data.current.question_version_id', null);

    $cat = $r4->json('data.progress.progress_data.cat');
    expect($cat['status'])->toBe('completed');
    expect($cat['current_section_id'])->toBeNull();
    expect($cat['sections'][(string) $ctx['section2']->section_id]['status'])->toBe('completed');

    // No further pending item exists on the session at all.
    expect(
        ExamSessionItem::query()->where('session_id', $sessionId)->where('item_state', 'pending')->count()
    )->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 6. Misconfiguration: section with no blueprint
|--------------------------------------------------------------------------
*/
it('throws a clear error when an adaptive section has no blueprint rows', function (): void {
    $admin = $this->createUser($this->tenantA, overrides: ['user_type' => 'admin']);
    $candidate = $this->createUser($this->tenantA);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
        'is_adaptive_exam' => true,
    ]);

    // Section exists but NO ExamBlueprint row is ever created for it.
    $this->createExamSection((string) $exam->exam_id, $this->tenantA, ['section_sequence' => 1]);

    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $service = app(ExamSessionService::class);

    expect(fn () => $service->startSession($this->tenantA, (string) $candidate->id, (string) $exam->exam_id))
        ->toThrow(RuntimeException::class, 'has no blueprint rows');
});

/*
|--------------------------------------------------------------------------
| 7. Misconfiguration: no eligible auto-gradable calibrated item
|--------------------------------------------------------------------------
*/
it('throws a clear error when the adaptive pool has no eligible calibrated auto-gradable item', function (): void {
    $admin = $this->createUser($this->tenantA, overrides: ['user_type' => 'admin']);
    $candidate = $this->createUser($this->tenantA);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
        'is_adaptive_exam' => true,
    ]);

    $section = $this->createExamSection((string) $exam->exam_id, $this->tenantA, ['section_sequence' => 1]);
    $category = $this->createCategoryForCat($this->tenantA);
    $competency = $this->createCompetencyForCat($this->tenantA, (string) $admin->id);

    $this->createAdaptiveBlueprint((string) $exam->exam_id, (string) $section->section_id, $competency);

    // Only an UNCALIBRATED item exists in the pool — no eligible candidate.
    $this->createCalibratedVersion(
        $this->tenantA, $category, (string) $admin->id, $competency,
        questionType: 'mcq', isCalibrated: false,
    );

    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $service = app(ExamSessionService::class);

    expect(fn () => $service->startSession($this->tenantA, (string) $candidate->id, (string) $exam->exam_id))
        ->toThrow(RuntimeException::class, 'no eligible calibrated');
});

it('throws a clear error when the adaptive pool only has essay-type (non-auto-gradable) items', function (): void {
    $admin = $this->createUser($this->tenantA, overrides: ['user_type' => 'admin']);
    $candidate = $this->createUser($this->tenantA);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
        'is_adaptive_exam' => true,
    ]);

    $section = $this->createExamSection((string) $exam->exam_id, $this->tenantA, ['section_sequence' => 1]);
    $category = $this->createCategoryForCat($this->tenantA);
    $competency = $this->createCompetencyForCat($this->tenantA, (string) $admin->id);

    $this->createAdaptiveBlueprint((string) $exam->exam_id, (string) $section->section_id, $competency);

    // Calibrated, but an essay — not auto-gradable, so CAT_SUPPORTED_QUESTION_TYPES excludes it.
    $this->createCalibratedVersion(
        $this->tenantA, $category, (string) $admin->id, $competency,
        questionType: 'essay', isCalibrated: true,
    );

    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $service = app(ExamSessionService::class);

    expect(fn () => $service->startSession($this->tenantA, (string) $candidate->id, (string) $exam->exam_id))
        ->toThrow(RuntimeException::class, 'no eligible calibrated');
});

/*
|--------------------------------------------------------------------------
| 8. Fixed/randomized exams are unaffected
|--------------------------------------------------------------------------
*/
it('still delivers a fixed (non-adaptive) exam correctly, unaffected by the adaptive changes', function (): void {
    $admin = $this->createUser($this->tenantA, overrides: ['user_type' => 'admin']);
    $candidate = $this->createUser($this->tenantA);

    $exam = $this->createExam($this->tenantA, (string) $admin->id, [
        'exam_status' => ExamStatus::Published,
        'is_published' => true,
        'is_adaptive_exam' => false,
    ]);

    $section = $this->createExamSection((string) $exam->exam_id, $this->tenantA, ['section_sequence' => 1]);
    $category = $this->createCategoryForCat($this->tenantA);
    $competency = $this->createCompetencyForCat($this->tenantA, (string) $admin->id);
    $versionId = $this->createCalibratedVersion($this->tenantA, $category, (string) $admin->id, $competency);

    $this->mock(QuestionSelectionService::class)
        ->shouldReceive('resolveQuestionsForSession')
        ->andReturn(collect([(object) [
            'sectionId' => (string) $section->section_id,
            'questionVersionId' => $versionId,
        ]]));

    $this->createEnrollment($this->tenantA, (string) $exam->exam_id, (string) $candidate->id);

    $this->grantPermissionsToUser($candidate, ['exam_sessions.start']);
    Sanctum::actingAs($candidate);

    $start = $this->postJson('/api/v1/exam-sessions/', ['exam_id' => (string) $exam->exam_id])
        ->assertCreated();

    // All items are pre-created for a fixed exam — exactly one here, and no
    // 'cat' key at all in progress_data (this path never touches CAT state).
    expect(ExamSessionItem::query()->where('session_id', $start->json('data.session_id'))->count())->toBe(1);
    expect($start->json('data.progress.progress_data.cat'))->toBeNull();

    $sessionId = $start->json('data.session_id');
    $itemId = $start->json('data.current.session_item_id');

    submitCorrect($sessionId, $itemId)
        ->assertOk()
        ->assertJsonPath('data.progress.total_questions_responded', 1);
});

/*
|--------------------------------------------------------------------------
| 9. Cross-tenant isolation
|--------------------------------------------------------------------------
*/
it('keeps an adaptive session isolated from other tenants', function (): void {
    $ctxA = setUpTwoSectionAdaptiveExam();
    $startA = startAdaptive($ctxA);
    $sessionIdA = $startA->json('data.session_id');

    // Switch to tenant B and try to view/act on tenant A's adaptive session.
    $this->initializeTenantContext($this->tenantB);
    $userB = $this->createUser($this->tenantB);
    $this->grantPermissionsToUser($userB, ['exam_sessions.view', 'exam_sessions.manage']);
    Sanctum::actingAs($userB);

    $this->getJson('/api/v1/exam-sessions/' . $sessionIdA)
        ->assertNotFound();
});