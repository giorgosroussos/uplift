<?php

use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-022 with the real quiz type: the teacher enters the
| basics, the quiz settings, writes a question, copies one from the bank,
| orders them by drag and drop and publishes; returning to the wizard
| restores every step.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
    $this->actingAsTenant($this->organization);
    $this->teacher = User::factory()->teacher()->create(['locale' => 'en']);
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    $this->bankQuestion = Question::factory()->for($this->teacher, 'owner')
        ->withOptions([['Athens', true], ['Sparta', false]])
        ->create(['prompt' => 'Capital of Greece?']);
});

it('builds a quiz from settings, a new and a copied question, and publishes it', function () {
    $this->actingAs($this->teacher);

    visit('/activities/create')
        ->select('@wizard-course-space', $this->space->id)
        ->fill('@wizard-title', 'Sorting quiz')
        ->click('@wizard-next')
        ->assertVisible('@quiz-settings')
        ->assertSee('Self-paced')
        ->fill('@quiz-max-attempts', '3')
        ->fill('@quiz-time-limit', '20')
        ->click('@wizard-next')
        ->assertVisible('@quiz-questions')
        ->assertSee('This quiz has no questions yet.')
        ->click('@quiz-add-question')
        ->fill('@question-prompt', 'Which sort is stable?')
        ->fill('@option-text-0', 'Merge sort')
        ->fill('@option-text-1', 'Quicksort')
        ->click('@option-correct-0')
        ->click('@quiz-open-bank')
        ->assertSee('Capital of Greece?')
        ->click('@quiz-bank-copy')
        ->assertSeeIn('@quiz-question-title-1', 'Capital of Greece?')
        ->drag('@quiz-question-handle-1', '@quiz-question-handle-0')
        ->assertSeeIn('@quiz-question-title-0', 'Capital of Greece?')
        ->click('@wizard-next')
        ->assertVisible('@wizard-review')
        ->click('@wizard-publish')
        ->assertNoJavaScriptErrors();

    $activity = Activity::query()->sole();
    $quiz = Quiz::query()->sole();
    $prompts = QuizQuestion::query()->orderBy('position')->with('question')->get()->map(fn (QuizQuestion $q) => $q->question->prompt)->all();

    expect($activity->refresh()->status)->toBe(ActivityStatus::Published)
        ->and($quiz->max_attempts)->toBe(3)
        ->and($quiz->time_limit)->toBe(1200)
        ->and($prompts)->toBe(['Capital of Greece?', 'Which sort is stable?'])
        ->and(Question::query()->where('in_bank', false)->count())->toBe(2)
        ->and(Question::query()->where('in_bank', true)->sole()->id)->toBe($this->bankQuestion->id);

    // Returning to the wizard restores the saved steps.
    visit("/activities/{$activity->id}/edit?step=content")
        ->assertSeeIn('@quiz-question-title-0', 'Capital of Greece?')
        ->assertSeeIn('@quiz-question-title-1', 'Which sort is stable?')
        ->click('@wizard-step-settings')
        ->assertValue('@quiz-max-attempts', '3')
        ->assertValue('@quiz-time-limit', '20')
        ->assertNoJavaScriptErrors();
});

it('refuses to publish a quiz without questions', function () {
    $this->actingAs($this->teacher);

    visit('/activities/create')
        ->select('@wizard-course-space', $this->space->id)
        ->fill('@wizard-title', 'Empty quiz')
        ->click('@wizard-next')
        ->assertVisible('@quiz-settings')
        ->click('@wizard-next')
        ->assertVisible('@quiz-questions')
        ->click('@wizard-next')
        ->assertVisible('@wizard-review')
        ->click('@wizard-publish')
        ->assertSee('A quiz needs at least one question.')
        ->assertNoJavaScriptErrors();

    expect(Activity::query()->sole()->status)->toBe(ActivityStatus::Draft);
});
