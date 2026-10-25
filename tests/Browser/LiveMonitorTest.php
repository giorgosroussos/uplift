<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Live\Actions\JoinLiveQuiz;
use App\Modules\Live\Actions\SubmitLiveParticipation;
use App\Modules\Live\Enums\LiveSessionStatus;
use App\Modules\Live\Models\LiveSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of UC-024 on the teacher's monitor page: open the lobby,
| see a student join, start, follow the progress and see the result.
| Without a websocket in the test build the page runs on its 2-second
| polling fallback (ARCH-006).
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
    $this->student = User::factory()->student()->create(['locale' => 'en', 'nickname' => 'Owl']);
    $space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Geography']);
    Enrolment::factory()->for($space, 'courseSpace')->for($this->student, 'student')->create();

    $this->activity = Activity::factory()->for($space, 'courseSpace')->published()->create(['title' => 'Capitals live']);
    Quiz::factory()->for($this->activity, 'activity')->live()
        ->withQuestions(1, Question::factory()->withOptions([['Athens', true], ['Sparta', false]])->state(['prompt' => 'Capital of Greece?']))
        ->create();
});

it('opens the lobby, sees a student join, starts, follows the progress and shows the result', function () {
    $this->actingAs($this->teacher);

    // ARCH-009: the owning teacher reaches the monitor from the activity page.
    $page = visit("/activities/{$this->activity->id}")
        ->assertMissing('@activity-action-open_quiz')
        ->click('@activity-action-open_live_quiz')
        ->assertPathIs("/live/quizzes/{$this->activity->id}")
        ->assertSee('Capitals live')
        ->click('@live-open-lobby')
        ->assertVisible('@live-lobby')
        ->assertSee('Waiting for students to join.');

    $participant = app(JoinLiveQuiz::class)->handle($this->student, $this->activity);

    $page->assertSeeIn('@live-lobby-participant', 'Owl')
        ->click('@live-start')
        ->assertVisible('@live-progress')
        ->assertVisible('@live-countdown')
        ->assertSeeIn('@live-progress-answered', '0 of 1')
        ->assertSeeIn('@live-progress-state', 'Answering');

    expect(LiveSession::query()->sole()->status)->toBe(LiveSessionStatus::Running);

    app(SubmitLiveParticipation::class)->handle($participant->refresh());

    $page->assertVisible('@live-results')
        ->assertSeeIn('@live-podium', 'Owl')
        ->assertSeeIn('@live-result-score', '0.00')
        ->assertSee('Capital of Greece?')
        ->assertNoJavaScriptErrors();
});
