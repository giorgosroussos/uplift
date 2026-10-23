<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Courses\Actions\CreateGroup;
use App\Modules\Courses\Actions\InviteToGroup;
use App\Modules\Courses\Actions\RespondToGroupInvitation;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flow of a group quiz (P4-T6, SCOPE-012, SCOPE-025): a student in
| two groups picks the group the attempt belongs to, and the attempt names
| it. See tests/Browser/TakeQuizTest.php for the "127" organisation.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
    $this->actingAsTenant($this->organization);
    $teacher = User::factory()->teacher()->create(['locale' => 'en']);
    $this->student = User::factory()->student()->create(['locale' => 'en']);
    $mate = User::factory()->student()->create(['locale' => 'en']);
    $space = CourseSpace::factory()->for($teacher, 'teacher')->create(['title' => 'Geography']);

    foreach ([$this->student, $mate] as $student) {
        Enrolment::factory()->for($space, 'courseSpace')->for($student, 'student')->create();
    }

    $this->alpha = app(CreateGroup::class)->handle($this->student, $space, 'Alpha');
    $this->beta = app(CreateGroup::class)->handle($this->student, $space, 'Beta');
    $invitation = app(InviteToGroup::class)->handle($this->student, $this->beta, $mate->id);
    app(RespondToGroupInvitation::class)->accept($mate, $invitation);

    $this->activity = Activity::factory()->for($space, 'courseSpace')->published()
        ->create(['title' => 'Team quiz', 'is_group' => true]);
    Quiz::factory()->for($this->activity, 'activity')->withQuestions(1)->create();
});

it('lets a student in two groups pick the group the attempt belongs to', function () {
    $this->actingAs($this->student);

    visit("/quizzes/{$this->activity->id}")
        ->assertSee('Team quiz')
        ->assertVisible('@quiz-group-notice')
        ->select('@attempt-group-select', $this->beta->id)
        ->click('@attempt-start')
        ->assertSeeIn('@attempt-group', 'Group attempt of Beta')
        ->assertNoJavaScriptErrors();

    expect(QuizAttempt::query()->sole()->group_id)->toBe($this->beta->id);
});
