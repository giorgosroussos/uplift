<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-012: a student opens a published quiz from its
| activity page, starts an attempt, answers (autosaved), submits and sees
| the score at once (default visibility).
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
    $this->actingAsTenant($this->organization);
    $teacher = User::factory()->teacher()->create(['locale' => 'en']);
    $this->student = User::factory()->student()->create(['locale' => 'en']);
    $space = CourseSpace::factory()->for($teacher, 'teacher')->create(['title' => 'Geography']);
    Enrolment::factory()->for($space, 'courseSpace')->for($this->student, 'student')->create();

    $this->activity = Activity::factory()->for($space, 'courseSpace')->published()->create(['title' => 'Capitals quiz']);
    Quiz::factory()->for($this->activity, 'activity')
        ->withQuestions(1, Question::factory()->withOptions([['Athens', true], ['Sparta', false]])->state(['prompt' => 'Capital of Greece?']))
        ->withQuestions(1, Question::factory()->trueFalse()->state(['prompt' => 'Paris is in France.']))
        ->create(['time_limit' => 600, 'max_attempts' => 1]);
});

it('takes a quiz, autosaves the answers, submits and shows the score', function () {
    $this->actingAs($this->student);

    $page = visit("/activities/{$this->activity->id}")
        ->click('@activity-action-open_quiz')
        ->assertSee('Capitals quiz')
        ->click('@attempt-start')
        ->assertVisible('@attempt-timer')
        ->assertSee('Capital of Greece?')
        ->click('@attempt-option-1-1')
        ->assertSeeIn('@attempt-save-1', 'Saved')
        ->click('@attempt-option-2-2')
        ->assertSeeIn('@attempt-save-2', 'Saved');

    expect(Answer::query()->count())->toBe(2);

    $page->click('@attempt-submit')
        ->assertSee('You answered 2 of 2 questions.')
        ->click('@attempt-submit-confirm')
        ->assertSeeIn('@attempt-score', 'Your score: 1.00 / 2.00 points')
        ->click('@attempt-back')
        ->assertSee('You have no attempts left.')
        ->assertNoJavaScriptErrors();

    expect(QuizAttempt::query()->sole()->status)->toBe(AttemptStatus::Graded);
});
