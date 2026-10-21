<?php

use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/*
| OWN-016 (ENT-Question) and DATA-008: a teacher reads, edits and deletes
| only their own bank questions; org admins and students have no access to
| any question bank. Plus tenant isolation of ENT-Question,
| ENT-ChoiceOption and ENT-MatchingPair (DATA-007, SEC-004).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacherA = User::factory()->teacher()->create();
    $this->teacherB = User::factory()->teacher()->create();
    $this->admin = User::factory()->orgAdmin()->create();
    $this->student = User::factory()->student()->create();

    $this->question = Question::factory()->for($this->teacherA, 'owner')
        ->withOptions([['Athens', true], ['Sparta', false]])
        ->create(['prompt' => 'Capital of Greece?']);
});

function ownershipBankUrl(string $path = ''): string
{
    return test()->tenantUrl(test()->acme, rtrim("question-bank/{$path}", '/'));
}

/**
 * @return array<string, mixed>
 */
function questionSnapshot(): array
{
    return [
        'questions' => Question::query()->withoutGlobalScopes()->orderBy('id')
            ->get(['id', 'owner_id', 'type', 'prompt', 'points', 'in_bank'])->toArray(),
        'options' => ChoiceOption::query()->withoutGlobalScopes()->orderBy('id')
            ->get(['id', 'question_id', 'text', 'is_correct', 'position'])->toArray(),
        'pairs' => MatchingPair::query()->withoutGlobalScopes()->orderBy('id')
            ->get(['id', 'question_id', 'left_text', 'right_text', 'position'])->toArray(),
    ];
}

/**
 * @return array<string, mixed>
 */
function hijackPayload(): array
{
    return [
        'type' => 'single_choice',
        'prompt' => 'Hijacked',
        'points' => '5',
        'options' => [
            ['text' => 'X', 'is_correct' => true],
            ['text' => 'Y', 'is_correct' => false],
        ],
    ];
}

/**
 * Every read and write of the question an actor without access might
 * attempt; each is refused.
 */
function attemptQuestionAccess(User $actor, Question $question): void
{
    test()->actingAs($actor)->get(ownershipBankUrl("{$question->id}/edit"))->assertForbidden();
    test()->actingAs($actor)->put(ownershipBankUrl($question->id), hijackPayload())->assertForbidden();
    // Refused before validation: an invalid payload reveals nothing either.
    test()->actingAs($actor)->put(ownershipBankUrl($question->id), [])->assertForbidden();
    test()->actingAs($actor)->delete(ownershipBankUrl($question->id))->assertForbidden();

    expect(Gate::forUser($actor)->allows('view', $question))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('update', $question))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('delete', $question))->toBeFalse();
}

describe('OWN-016 / UC-023: another teacher', function () {
    it('cannot open, edit or delete a bank question of teacher A, and nothing changes', function () {
        $before = questionSnapshot();

        attemptQuestionAccess($this->teacherB, $this->question);

        expect(questionSnapshot())->toBe($before);
    });

    it('does not see teacher A\'s questions in their own bank', function () {
        $this->actingAs($this->teacherB)
            ->get(ownershipBankUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('questions.data', 0));
    });

    it('creates questions only in their own bank', function () {
        $this->actingAs($this->teacherB)->post(ownershipBankUrl(), [...hijackPayload(), 'owner_id' => $this->teacherA->id, 'in_bank' => false]);

        $created = Question::query()->where('prompt', 'Hijacked')->sole();

        expect($created->owner_id)->toBe($this->teacherB->id)
            ->and($created->in_bank)->toBeTrue()
            ->and(Question::query()->where('owner_id', $this->teacherA->id)->pluck('id')->all())->toBe([$this->question->id]);
    });

    it('lets the owner open, edit and delete the same question', function () {
        expect(Gate::forUser($this->teacherA)->allows('view', $this->question))->toBeTrue()
            ->and(Gate::forUser($this->teacherA)->allows('update', $this->question))->toBeTrue()
            ->and(Gate::forUser($this->teacherA)->allows('delete', $this->question))->toBeTrue();

        $this->actingAs($this->teacherA)->get(ownershipBankUrl("{$this->question->id}/edit"))->assertOk();
    });
});

describe('OWN-016: a teacher and questions outside their bank', function () {
    it('cannot reach their own quiz copy (in_bank = false) through the bank', function () {
        $copy = Question::factory()->for($this->teacherA, 'owner')->quizCopy()->create(['prompt' => 'Copy']);
        $before = questionSnapshot();

        attemptQuestionAccess($this->teacherA, $copy);

        expect(questionSnapshot())->toBe($before);
    });

    it('cannot reach a bank question of another organisation', function () {
        $beta = $this->createOrganization(['subdomain' => 'beta']);
        $foreign = $this->asTenant($beta, fn () => Question::factory()->create(['prompt' => 'Beta question']));
        $before = questionSnapshot();

        $this->actingAs($this->teacherA)->get(ownershipBankUrl("{$foreign->id}/edit"))->assertNotFound();
        $this->actingAs($this->teacherA)->put(ownershipBankUrl($foreign->id), hijackPayload())->assertNotFound();
        $this->actingAs($this->teacherA)->delete(ownershipBankUrl($foreign->id))->assertNotFound();

        expect(questionSnapshot())->toBe($before);
    });

    it('is refused the bank once deactivated', function () {
        $this->teacherA->forceFill(['is_active' => false])->save();

        expect(Gate::forUser($this->teacherA)->allows('viewAny', Question::class))->toBeFalse()
            ->and(Gate::forUser($this->teacherA)->allows('update', $this->question))->toBeFalse();
    });
});

describe('OWN-016: org admins and students have no question bank', function () {
    it('refuses the bank pages and every bank question', function (string $role) {
        $actor = $this->{$role};
        $before = questionSnapshot();

        $this->actingAs($actor)->get(ownershipBankUrl())->assertForbidden();
        $this->actingAs($actor)->get(ownershipBankUrl('create'))->assertForbidden();
        $this->actingAs($actor)->post(ownershipBankUrl(), hijackPayload())->assertForbidden();
        $this->actingAs($actor)->post(ownershipBankUrl(), [])->assertForbidden();
        attemptQuestionAccess($actor, $this->question);

        expect(questionSnapshot())->toBe($before)
            ->and(Gate::forUser($actor)->allows('viewAny', Question::class))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('create', Question::class))->toBeFalse();
    })->with(['admin', 'student']);
});

describe('tenant isolation (DATA-007)', function () {
    it('isolates questions per organisation', function () {
        $this->assertTenantIsolated(Question::class, fn () => Question::factory()->freeText()->create());
    });

    it('isolates choice options per organisation', function () {
        $this->assertTenantIsolated(ChoiceOption::class, fn () => ChoiceOption::factory()->create());
    });

    it('isolates matching pairs per organisation', function () {
        $this->assertTenantIsolated(MatchingPair::class, fn () => MatchingPair::factory()->create());
    });

    it('never stores an option under a question of another organisation', function () {
        $beta = $this->createOrganization(['subdomain' => 'beta']);
        $foreign = $this->asTenant($beta, fn () => Question::factory()->freeText()->create());

        $this->withoutTenant();

        expect(fn () => ChoiceOption::query()->withoutGlobalScopes()->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->acme->id,
            'question_id' => $foreign->id,
            'text' => 'x',
            'is_correct' => false,
            'position' => 0,
        ]))->toThrow(QueryException::class);
    });
});
