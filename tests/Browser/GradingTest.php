<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Actions\SubmitQuizAttempt;
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
| Browser flow of UC-028 / UC-026 / UC-017: the teacher opens the quiz's
| submissions, filters those needing grading, grades the free-text answer
| with feedback; the student then sees the final grade on the 0-10 scale
| and the feedback in My results.
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
    $this->student = User::factory()->student()->create(['locale' => 'en', 'name' => 'Eleni Student']);
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'History']);
    Enrolment::factory()->for($space, 'courseSpace')->for($this->student, 'student')->create();

    $this->activity = Activity::factory()->for($space, 'courseSpace')->published()->create(['title' => 'Essay quiz']);
    $quiz = Quiz::factory()->for($this->activity, 'activity')
        ->withQuestions(1, Question::factory()->freeText()->state(['prompt' => 'Explain the causes.', 'points' => '8.00']))
        ->create();

    $this->attempt = QuizAttempt::factory()->for($quiz, 'quiz')->for($this->student, 'student')->create(['max_points' => '8.00']);
    Answer::factory()->for($this->attempt, 'attempt')->create(['text_response' => 'Many causes.']);
    app(SubmitQuizAttempt::class)->handle($this->attempt, $this->student);
});

it('grades a free-text answer from the submissions overview and shows the final grade to the student', function () {
    expect($this->attempt->refresh()->status)->toBe(AttemptStatus::Submitted);

    $this->actingAs($this->teacher);

    visit("/activities/{$this->activity->id}/submissions")
        ->click('@filter-needs_grading')
        ->assertSee('Eleni Student')
        ->click('@submission-open')
        ->assertSee('Many causes.')
        ->assertSeeIn('@review-status-1', 'Awaiting grading')
        ->fill('@grade-points-1', '7')
        ->fill('@grade-feedback-1', 'Well argued.')
        ->click('@grade-save-1')
        ->assertSeeIn('@grading-status', 'Final')
        ->assertSeeIn('@grading-grade', '8.8 / 10')
        ->assertNoJavaScriptErrors();

    expect($this->attempt->refresh()->status)->toBe(AttemptStatus::Graded);

    $this->actingAs($this->student);

    visit('/results')
        ->assertSeeIn('@result-grade', '8.8 / 10')
        ->assertSeeIn('@result-status', 'Final')
        ->click('@result-open')
        ->assertSeeIn('@review-feedback-1', 'Well argued.')
        ->assertNoJavaScriptErrors();
});
