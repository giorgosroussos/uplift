<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Courses\Actions\CreateGroup;
use App\Modules\Courses\Actions\DissolveGroup;
use App\Modules\Courses\Actions\InviteToGroup;
use App\Modules\Courses\Actions\LeaveGroup;
use App\Modules\Courses\Actions\RespondToGroupInvitation;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Courses\Models\Group;
use App\Modules\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| P4-T6: group quiz attempts (SCOPE-012, SCOPE-025, OWN-006). In a group
| quiz the acting student picks which of their groups the attempt belongs
| to; any member answers and submits for the group and every member gets
| the same grade; a group may not start while a current member takes part
| through another group; a member at submission keeps seeing the attempt
| after leaving or the group's deletion, a later member does not.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00'));

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();

    // X is in G1 and G2; A is in G1; B is in G2; Y joins later.
    foreach (['x', 'a', 'b', 'y', 'outsider'] as $name) {
        $this->{$name} = User::factory()->student()->create();
        Enrolment::factory()->for($this->space, 'courseSpace')->for($this->{$name}, 'student')->create();
    }

    $this->g1 = gqGroup($this->x, 'Alpha', [$this->a]);
    $this->g2 = gqGroup($this->x, 'Beta', [$this->b]);
});

function gqUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A group created by $owner with the given members, each joining a minute
 * apart.
 *
 * @param  list<User>  $members
 */
function gqGroup(User $owner, string $name, array $members = [], ?CourseSpace $space = null): Group
{
    $group = app(CreateGroup::class)->handle($owner, $space ?? test()->space, $name);

    foreach ($members as $member) {
        gqJoin($group, $member, $owner);
    }

    return $group;
}

function gqJoin(Group $group, User $student, User $inviter): void
{
    test()->travel(1)->minutes();
    $invitation = app(InviteToGroup::class)->handle($inviter, $group, $student->id);
    app(RespondToGroupInvitation::class)->accept($student, $invitation);
}

/**
 * @param  array<string, mixed>  $settings
 * @param  array<string, mixed>  $activity
 */
function gqQuiz(array $settings = [], array $activity = []): Quiz
{
    $activity = Activity::factory()->for(test()->space, 'courseSpace')->published()
        ->create(['is_group' => true, ...$activity]);

    return Quiz::factory()->for($activity, 'activity')->withQuestions(1)->create($settings);
}

function gqStart(Quiz $quiz, User $as, ?Group $group): TestResponse
{
    return test()->actingAs($as)->post(
        gqUrl("quizzes/{$quiz->activity_id}/attempts"),
        $group === null ? [] : ['group_id' => $group->id],
    );
}

function gqAttempt(Quiz $quiz): QuizAttempt
{
    return QuizAttempt::query()->where('quiz_id', $quiz->activity_id)->latest('started_at')->firstOrFail();
}

/**
 * Starts an attempt for the group, answers the single choice question
 * correctly and submits it (auto-graded: graded at once).
 */
function gqSubmitted(Quiz $quiz, Group $group, User $by): QuizAttempt
{
    gqStart($quiz, $by, $group)->assertSessionHasNoErrors();
    $attempt = gqAttempt($quiz);

    /** @var QuizQuestion $placement */
    $placement = $quiz->quizQuestions()->with('question.options')->firstOrFail();
    $correct = $placement->question->options->firstWhere('text', 'A');

    test()->actingAs($by)
        ->putJson(gqUrl("quiz-attempts/{$attempt->id}/answers/{$placement->id}"), ['option_ids' => [$correct->id]])
        ->assertOk();
    test()->actingAs($by)->post(gqUrl("quiz-attempts/{$attempt->id}/submit"))->assertRedirect();

    return $attempt->refresh();
}

describe('choosing the acting group', function () {
    it('offers each of the student\'s groups and lets them pick which one the attempt belongs to', function () {
        $quiz = gqQuiz();

        $this->actingAs($this->x)->get(gqUrl("quizzes/{$quiz->activity_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assessment/Attempts/Quiz')
                ->where('quiz.is_group', true)
                ->where('canStart', true)
                ->has('groups', 2)
                ->where('groups.0.id', $this->g1->id)
                ->where('groups.0.refusal', null)
                ->where('groups.1.id', $this->g2->id)
                ->where('groups.1.refusal', null));

        gqStart($quiz, $this->x, $this->g2)->assertSessionHasNoErrors();

        $attempt = gqAttempt($quiz);
        expect($attempt->group_id)->toBe($this->g2->id)
            ->and($attempt->student_id)->toBeNull()
            ->and($attempt->status)->toBe(AttemptStatus::InProgress);
    });

    it('refuses a group quiz attempt without a group, or with a group the student is not a member of', function () {
        $quiz = gqQuiz();
        $foreign = gqGroup($this->outsider, 'Gamma');
        $otherSpace = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
        Enrolment::factory()->for($otherSpace, 'courseSpace')->for($this->x, 'student')->create();
        $elsewhere = gqGroup($this->x, 'Delta', [], $otherSpace);

        gqStart($quiz, $this->x, null)->assertSessionHasErrors('group_id');
        gqStart($quiz, $this->x, $foreign)->assertSessionHasErrors('group_id');
        gqStart($quiz, $this->x, $elsewhere)->assertSessionHasErrors('group_id');

        expect(QuizAttempt::query()->count())->toBe(0);
    });

    it('lets any member answer and submit for the group, and every member gets the same grade (SCOPE-012)', function () {
        $quiz = gqQuiz();
        gqStart($quiz, $this->x, $this->g1)->assertSessionHasNoErrors();
        $attempt = gqAttempt($quiz);

        // A, another member, continues and submits the group's attempt.
        $this->actingAs($this->a)->get(gqUrl("quiz-attempts/{$attempt->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('attempt.group', 'Alpha'));
        $this->actingAs($this->a)->post(gqUrl("quiz-attempts/{$attempt->id}/submit"))->assertRedirect();

        $attempt->refresh();
        expect($attempt->submitted_by)->toBe($this->a->id)
            ->and($attempt->status)->toBe(AttemptStatus::Graded);

        foreach ([$this->x, $this->a] as $member) {
            $this->actingAs($member)->get(gqUrl("results/{$attempt->id}"))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('attempt.group', 'Alpha')
                    ->where('score.earned', '0.00')
                    ->where('score.max', '1.00'));
        }

        // B is not in G1: neither the attempt nor its result.
        $this->actingAs($this->b)->get(gqUrl("quiz-attempts/{$attempt->id}"))->assertForbidden();
        $this->actingAs($this->b)->get(gqUrl("results/{$attempt->id}"))->assertForbidden();
    });

    it('counts attempts per group and shows the group as the participant in the submissions', function () {
        $quiz = gqQuiz(['max_attempts' => 1]);
        $attempt = gqSubmitted($quiz, $this->g1, $this->a);

        gqStart($quiz, $this->x, $this->g1)->assertSessionHasErrors('attempt');

        $this->actingAs($this->teacher)->get(gqUrl("activities/{$quiz->activity_id}/submissions"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('submissions.0.id', $attempt->id)
                ->where('submissions.0.participant', 'Alpha'));
    });

    it('refuses a group with more current members than the quiz\'s group_size', function () {
        $quiz = gqQuiz(activity: ['group_size' => 2]);
        gqJoin($this->g1, $this->y, $this->x);

        gqStart($quiz, $this->x, $this->g1)->assertSessionHasErrors([
            'attempt' => __('assessment.attempt.refused.group_too_large'),
        ]);
        gqStart($quiz, $this->x, $this->g2)->assertSessionHasNoErrors();
    });
});

describe('one group per student in a quiz', function () {
    it('refuses G2 when G1 has an attempt in the quiz and X is a member of both', function () {
        $quiz = gqQuiz(['max_attempts' => 3]);
        gqSubmitted($quiz, $this->g1, $this->a);

        gqStart($quiz, $this->b, $this->g2)->assertSessionHasErrors([
            'attempt' => __('assessment.attempt.refused.other_group'),
        ]);
        gqStart($quiz, $this->x, $this->g2)->assertSessionHasErrors('attempt');

        expect(QuizAttempt::query()->where('group_id', $this->g2->id)->exists())->toBeFalse();

        $this->actingAs($this->x)->get(gqUrl("quizzes/{$quiz->activity_id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('groups.0.id', $this->g1->id)
                ->where('groups.0.refusal', null)
                ->where('groups.1.id', $this->g2->id)
                ->where('groups.1.refusal', 'other_group'));

        // G1 itself may use its remaining attempts.
        gqStart($quiz, $this->x, $this->g1)->assertSessionHasNoErrors();
    });

    it('also refuses G2 while G1\'s attempt is still in progress', function () {
        $quiz = gqQuiz();
        gqStart($quiz, $this->a, $this->g1)->assertSessionHasNoErrors();

        gqStart($quiz, $this->b, $this->g2)->assertSessionHasErrors('attempt');
    });
});

describe('access after the membership ends (OWN-006)', function () {
    it('keeps the attempt and its grade visible to X after X leaves the group', function () {
        $quiz = gqQuiz();
        $attempt = gqSubmitted($quiz, $this->g2, $this->b);

        $this->travel(1)->minutes();
        app(LeaveGroup::class)->handle($this->b, $this->g2);
        // X, the owner and last member, leaves too: the group is deleted.
        $this->travel(1)->minutes();
        app(LeaveGroup::class)->handle($this->x, $this->g2->refresh());
        expect(Group::query()->find($this->g2->id))->toBeNull();

        foreach ([$this->b, $this->x] as $former) {
            $this->actingAs($former)->get(gqUrl("results/{$attempt->id}"))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('attempt.final', true)
                    ->where('score.earned', '1.00'));

            $this->actingAs($former)->get(gqUrl('results'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('results.0.attempts.0.id', $attempt->id)
                    ->where('results.0.attempts.0.group', 'Beta'));
        }
    });

    it('keeps the attempt and its grade visible to its members after the group is deleted', function () {
        $quiz = gqQuiz();
        $attempt = gqSubmitted($quiz, $this->g1, $this->x);

        $this->travel(1)->minutes();
        app(DissolveGroup::class)->handle($this->g1);
        expect(Group::query()->find($this->g1->id))->toBeNull();

        foreach ([$this->x, $this->a] as $member) {
            $this->actingAs($member)->get(gqUrl("results/{$attempt->id}"))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('attempt.group', 'Alpha')
                    ->where('score.earned', '1.00'));
            $this->actingAs($member)->get(gqUrl("quiz-attempts/{$attempt->id}"))->assertOk();
        }
    });

    it('refuses the attempt to Y, who joined after it was submitted and later left', function () {
        $quiz = gqQuiz();
        $attempt = gqSubmitted($quiz, $this->g1, $this->a);

        $this->travel(1)->minutes();
        gqJoin($this->g1, $this->y, $this->x);

        // While a member, the attempts of their group are visible.
        $this->actingAs($this->y)->get(gqUrl("results/{$attempt->id}"))->assertOk();

        $this->travel(1)->minutes();
        app(LeaveGroup::class)->handle($this->y, $this->g1);

        $this->actingAs($this->y)->get(gqUrl("results/{$attempt->id}"))->assertForbidden();
        $this->actingAs($this->y)->get(gqUrl("quiz-attempts/{$attempt->id}"))->assertForbidden();
        $this->actingAs($this->y)->get(gqUrl('results'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('results', []));
    });

    it('refuses a member who left before the submission', function () {
        $quiz = gqQuiz();
        gqStart($quiz, $this->x, $this->g1)->assertSessionHasNoErrors();
        $attempt = gqAttempt($quiz);

        $this->travel(1)->minutes();
        app(LeaveGroup::class)->handle($this->a, $this->g1);

        $this->travel(1)->minutes();
        $this->actingAs($this->a)->post(gqUrl("quiz-attempts/{$attempt->id}/submit"))->assertForbidden();
        $this->actingAs($this->x)->post(gqUrl("quiz-attempts/{$attempt->id}/submit"))->assertRedirect();

        $this->actingAs($this->a)->get(gqUrl("results/{$attempt->id}"))->assertForbidden();
    });

    it('never lets a group or a student of another organisation act in or read a group attempt', function () {
        $quiz = gqQuiz();
        $attempt = gqSubmitted($quiz, $this->g1, $this->a);

        $globex = $this->createOrganization(['subdomain' => 'globex']);
        $this->actingAsTenant($globex);
        $foreignTeacher = User::factory()->teacher()->create();
        $foreignStudent = User::factory()->student()->create();
        $foreignSpace = CourseSpace::factory()->for($foreignTeacher, 'teacher')->create();
        Enrolment::factory()->for($foreignSpace, 'courseSpace')->for($foreignStudent, 'student')->create();
        $foreignGroup = app(CreateGroup::class)->handle($foreignStudent, $foreignSpace, 'Omega');

        expect(QuizAttempt::query()->count())->toBe(0)
            ->and(Gate::forUser($foreignStudent)->allows('viewResult', $attempt))->toBeFalse()
            ->and(Gate::forUser($foreignStudent)->allows('view', $attempt))->toBeFalse();

        $this->actingAsTenant($this->acme);
        gqStart($quiz, $this->x, $foreignGroup)->assertSessionHasErrors('group_id');
        expect(QuizAttempt::query()->where('group_id', $foreignGroup->id)->exists())->toBeFalse();
    });
});
