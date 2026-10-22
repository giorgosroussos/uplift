<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\AttemptStatus;
use App\Modules\Assessment\Enums\ResultVisibility;
use App\Modules\Assessment\Events\ResultChanged;
use App\Modules\Assessment\Events\ResultReleased;
use App\Modules\Assessment\Models\Answer;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Actions\UpdateNotificationPreference;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Notifications\GradeChangedEmail;
use App\Modules\Notifications\Notifications\GradeFinalisedEmail;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

/*
| DEC-001 / DEC-003 / INT-001: when a quiz attempt's final score becomes
| visible to its student (ResultReleased), the student gets one in-app
| grade_finalised notification and, unless they turned notification emails
| off, one queued email in their locale; a regrade that changes a released
| result sends grade_changed. Neither carries the score (SEC-003).
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'default_locale' => 'el']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->student = User::factory()->student()->create(['email' => 'x@acme.test', 'locale' => 'en']);
    $this->classmate = User::factory()->student()->create(['email' => 'y@acme.test']);

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);

    foreach ([$this->student, $this->classmate] as $enrolled) {
        Enrolment::factory()->for($this->space, 'courseSpace')->for($enrolled, 'student')->create();
    }
});

function gnUrl(string $path): string
{
    return test()->tenantUrl(test()->acme, ltrim($path, '/'));
}

/**
 * A published self-paced quiz "Sorting quiz" with one 5-point question of
 * the given kind, the given score visibility and deadline.
 */
function gnQuiz(
    bool $freeText = false,
    ResultVisibility $visibility = ResultVisibility::Immediate,
    ?CarbonImmutable $deadline = null,
): Quiz {
    $question = $freeText ? Question::factory()->freeText() : Question::factory();

    return Quiz::factory()
        ->for(Activity::factory()->for(test()->space, 'courseSpace')->published()->create([
            'title' => 'Sorting quiz',
            'deadline_at' => $deadline,
        ]), 'activity')
        ->withQuestions(1, $question->state(['points' => '5.00']))
        ->create(['score_visibility' => $visibility]);
}

function gnRelease(): void
{
    test()->artisan('assessment:release-results')->assertSuccessful();
}

function gnCount(NotificationKind $kind = NotificationKind::GradeFinalised): int
{
    return Notification::query()->where('user_id', test()->student->id)->where('kind', $kind->value)->count();
}

function gnSubmit(Quiz $quiz, User $student): QuizAttempt
{
    test()->actingAs($student)->post(gnUrl("quizzes/{$quiz->activity_id}/attempts"))->assertRedirect();
    $attempt = QuizAttempt::query()->where('quiz_id', $quiz->activity_id)->where('student_id', $student->id)
        ->where('status', AttemptStatus::InProgress->value)->sole();

    /** @var QuizQuestion $placement */
    $placement = $quiz->quizQuestions()->with('question.options')->sole();
    $payload = $placement->question->options->isEmpty()
        ? ['text' => 'My essay']
        : ['option_ids' => $placement->question->options->where('text', 'A')->pluck('id')->all()];

    test()->actingAs($student)
        ->putJson(gnUrl("quiz-attempts/{$attempt->id}/answers/{$placement->id}"), $payload)
        ->assertOk();
    test()->actingAs($student)->post(gnUrl("quiz-attempts/{$attempt->id}/submit"))->assertRedirect();

    return $attempt->refresh();
}

function gnGrade(QuizAttempt $attempt, string $points = '4'): void
{
    $answer = Answer::query()->where('attempt_id', $attempt->id)->sole();

    test()->actingAs(test()->teacher)
        ->put(gnUrl("grading/answers/{$answer->id}"), ['points' => $points])
        ->assertSessionHasNoErrors();
}

it('gives the student one in-app entry and one queued email at once when an auto-graded attempt of an immediate quiz is submitted', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz();

    $attempt = gnSubmit($quiz, $this->student);
    expect($attempt->status)->toBe(AttemptStatus::Graded)
        ->and($attempt->result_released_at)->not->toBeNull();

    $entry = Notification::query()->sole();
    expect($entry->user_id)->toBe($this->student->id)
        ->and($entry->kind)->toBe(NotificationKind::GradeFinalised)
        ->and($entry->organization_id)->toBe($this->acme->id)
        // Ids and title snapshots only, never the score (SEC-003).
        ->and($entry->data)->toEqual([
            'course_space_id' => $this->space->id,
            'activity_id' => $quiz->activity_id,
            'attempt_id' => $attempt->id,
            'course_space_title' => 'Algorithms',
            'activity_title' => 'Sorting quiz',
        ]);

    expect(new GradeFinalisedEmail('u', 'a', 's', 't', 'l'))->toBeInstanceOf(ShouldQueue::class);

    NotificationFacade::assertSentOnDemandTimes(GradeFinalisedEmail::class, 1);
    NotificationFacade::assertSentOnDemand(
        GradeFinalisedEmail::class,
        fn (GradeFinalisedEmail $email, array $channels, AnonymousNotifiable $notifiable, ?string $locale): bool => $notifiable->routes['mail'] === 'x@acme.test'
            && $locale === 'en'
            && $channels === ['mail']
            && $email->attemptId === $attempt->id
            && $email->url === 'http://acme.uplift.test/results/'.$attempt->id,
    );
});

it('waits for the deadline with after_deadline visibility and notifies when the release command runs after it', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz(visibility: ResultVisibility::AfterDeadline, deadline: CarbonImmutable::now()->addHour());

    $attempt = gnSubmit($quiz, $this->student);

    expect($attempt->status)->toBe(AttemptStatus::Graded)
        ->and($attempt->result_released_at)->toBeNull()
        ->and(Notification::query()->count())->toBe(0);
    NotificationFacade::assertNothingSent();

    // Before the deadline the command releases nothing.
    gnRelease();
    expect(Notification::query()->count())->toBe(0);

    $this->travel(61)->minutes();
    gnRelease();

    expect(gnCount())->toBe(1)
        ->and(Notification::query()->count())->toBe(1)
        ->and($attempt->refresh()->result_released_at)->not->toBeNull();
    NotificationFacade::assertSentOnDemandTimes(GradeFinalisedEmail::class, 1);
});

it('notifies at once with after_deadline visibility and no deadline', function () {
    NotificationFacade::fake();

    gnSubmit(gnQuiz(visibility: ResultVisibility::AfterDeadline), $this->student);

    expect(gnCount())->toBe(1);
    NotificationFacade::assertSentOnDemandTimes(GradeFinalisedEmail::class, 1);
});

it('notifies at once when the last free-text answer is graded after the deadline passed', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz(freeText: true, visibility: ResultVisibility::AfterDeadline, deadline: CarbonImmutable::now()->addHour());
    $attempt = gnSubmit($quiz, $this->student);

    $this->travel(2)->hours();
    gnGrade($attempt);

    expect(gnCount())->toBe(1);
    NotificationFacade::assertSentOnDemandTimes(GradeFinalisedEmail::class, 1);
});

it('sends nothing for a provisional score when the deadline passes, and notifies once when the answer is graded', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz(freeText: true, visibility: ResultVisibility::AfterDeadline, deadline: CarbonImmutable::now()->addHour());
    $attempt = gnSubmit($quiz, $this->student);
    gnSubmit($quiz, $this->classmate);

    $this->travel(2)->hours();
    gnRelease();

    expect($attempt->refresh()->status)->toBe(AttemptStatus::Submitted)
        ->and(Notification::query()->count())->toBe(0);
    NotificationFacade::assertNothingSent();

    gnGrade($attempt);
    gnRelease();

    // Only the graded student; never the teacher or the still provisional classmate.
    expect(gnCount())->toBe(1)
        ->and(Notification::query()->count())->toBe(1);
    NotificationFacade::assertSentOnDemandTimes(GradeFinalisedEmail::class, 1);
});

it('never notifies with hidden visibility, and notifies once at the next release after the teacher makes it immediate', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz(visibility: ResultVisibility::Hidden, deadline: CarbonImmutable::now()->addHour());
    $attempt = gnSubmit($quiz, $this->student);

    $this->travel(2)->hours();
    gnRelease();

    expect($attempt->refresh()->status)->toBe(AttemptStatus::Graded)
        ->and($attempt->result_released_at)->toBeNull()
        ->and(Notification::query()->count())->toBe(0);
    NotificationFacade::assertNothingSent();

    $quiz->update(['score_visibility' => ResultVisibility::Immediate]);
    gnRelease();
    gnRelease();

    expect(gnCount())->toBe(1);
    NotificationFacade::assertSentOnDemandTimes(GradeFinalisedEmail::class, 1);
});

it('never sends a second grade_finalised for a released attempt', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz(freeText: true);
    $attempt = gnSubmit($quiz, $this->student);

    gnGrade($attempt, '4');
    expect(gnCount())->toBe(1);

    // The release command again, and the attempt finalised again (a regrade
    // with the same points).
    gnRelease();
    gnGrade($attempt, '4');
    $this->travel(5)->minutes();
    gnRelease();

    expect(gnCount())->toBe(1)
        ->and(gnCount(NotificationKind::GradeChanged))->toBe(0);
    NotificationFacade::assertSentOnDemandTimes(GradeFinalisedEmail::class, 1);
    NotificationFacade::assertSentOnDemandTimes(GradeChangedEmail::class, 0);
});

it('sends grade_changed when a regrade changes a released result, and nothing when it leaves earned_points unchanged', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz(freeText: true);
    $attempt = gnSubmit($quiz, $this->student);
    gnGrade($attempt, '4');

    // Same points, other feedback: earned_points unchanged.
    $answer = Answer::query()->where('attempt_id', $attempt->id)->sole();
    $this->actingAs($this->teacher)
        ->put(gnUrl("grading/answers/{$answer->id}"), ['points' => '4.00', 'feedback' => 'Well argued'])
        ->assertSessionHasNoErrors();

    expect(gnCount(NotificationKind::GradeChanged))->toBe(0);
    NotificationFacade::assertSentOnDemandTimes(GradeChangedEmail::class, 0);

    gnGrade($attempt, '3.5');

    $entry = Notification::query()->where('kind', NotificationKind::GradeChanged->value)->sole();
    expect($entry->user_id)->toBe($this->student->id)
        ->and($entry->data['attempt_id'])->toBe($attempt->id)
        ->and(gnCount())->toBe(1);
    NotificationFacade::assertSentOnDemandTimes(GradeChangedEmail::class, 1);
    NotificationFacade::assertSentOnDemand(
        GradeChangedEmail::class,
        fn (GradeChangedEmail $email, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'x@acme.test'
            && $email->attemptId === $attempt->id,
    );
});

it('sends nothing for regrades before the release, only the release itself', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz(freeText: true, visibility: ResultVisibility::AfterDeadline, deadline: CarbonImmutable::now()->addHour());
    $attempt = gnSubmit($quiz, $this->student);

    gnGrade($attempt, '4');
    gnGrade($attempt, '2');
    gnRelease();

    expect(Notification::query()->count())->toBe(0);
    NotificationFacade::assertNothingSent();

    $this->travel(2)->hours();
    gnRelease();

    expect(gnCount())->toBe(1)
        ->and(gnCount(NotificationKind::GradeChanged))->toBe(0);
    NotificationFacade::assertSentOnDemandTimes(GradeFinalisedEmail::class, 1);
    NotificationFacade::assertSentOnDemandTimes(GradeChangedEmail::class, 0);
});

it('follows a deadline moved later: nothing at the old deadline, the students at the new one', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz(visibility: ResultVisibility::AfterDeadline, deadline: CarbonImmutable::now()->addHour());
    $attempt = gnSubmit($quiz, $this->student);
    $other = gnSubmit($quiz, $this->classmate);

    $quiz->activity->update(['deadline_at' => CarbonImmutable::now()->addHours(3)]);

    $this->travel(2)->hours();
    gnRelease();

    expect(Notification::query()->count())->toBe(0);
    NotificationFacade::assertNothingSent();

    $this->travel(2)->hours();
    gnRelease();

    expect(Notification::query()->where('kind', NotificationKind::GradeFinalised->value)->pluck('user_id')->sort()->values()->all())
        ->toEqual(collect([$this->student->id, $this->classmate->id])->sort()->values()->all())
        ->and($attempt->refresh()->result_released_at)->not->toBeNull()
        ->and($other->refresh()->result_released_at)->not->toBeNull();
    NotificationFacade::assertSentOnDemandTimes(GradeFinalisedEmail::class, 2);
});

it('releases only visible attempts and none of a hidden quiz', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz(visibility: ResultVisibility::AfterDeadline, deadline: CarbonImmutable::now()->addHour());
    $attempt = gnSubmit($quiz, $this->student);
    $hidden = gnSubmit(gnQuiz(visibility: ResultVisibility::Hidden), $this->classmate);

    $this->travel(2)->hours();
    $this->artisan('assessment:release-results')->expectsOutput('Released 1 attempt(s).')->assertSuccessful();

    expect($attempt->refresh()->result_released_at)->not->toBeNull()
        ->and($hidden->refresh()->result_released_at)->toBeNull();
});

it('schedules the release command every minute', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'assessment:release-results'));

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('* * * * *');
});

it('sends the email in the student\'s locale with the activity, the space and the link but not the score', function () {
    $this->student->update(['locale' => null]);
    $quiz = gnQuiz(freeText: true);
    $attempt = gnSubmit($quiz, $this->student);

    gnGrade($attempt, '3.75');

    /** @var list<SentMessage> $messages */
    $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->all();
    expect($messages)->toHaveCount(1);

    $email = $messages[0]->getOriginalMessage();
    assert($email instanceof Email);

    // No locale of their own: the organisation's Greek default.
    expect($email->getTo()[0]->getAddress())->toBe('x@acme.test')
        ->and($email->getSubject())->toBe('Η προσπάθειά σας στο Sorting quiz βαθμολογήθηκε')
        ->and((string) $email->getTextBody())->toContain('Algorithms')
        ->toContain('http://acme.uplift.test/results/'.$attempt->id)
        ->not->toContain('3.75')
        ->and((string) $email->getHtmlBody())->not->toContain('3.75');
});

it('sends no email to a student who turned notification emails off, but still the in-app entry', function () {
    app(UpdateNotificationPreference::class)->handle($this->student, false);
    NotificationFacade::fake();

    gnSubmit(gnQuiz(), $this->student);

    expect(Notification::query()->where('user_id', $this->student->id)->count())->toBe(1);
    NotificationFacade::assertNothingSent();
});

it('opens the student\'s result from the notification, and nobody else may open it', function () {
    NotificationFacade::fake();
    $attempt = gnSubmit(gnQuiz(), $this->student);
    $entry = Notification::query()->sole();

    $this->actingAs($this->classmate)
        ->post(gnUrl("notifications/{$entry->id}/open"))
        ->assertForbidden();
    expect($entry->fresh()?->read_at)->toBeNull();

    $this->actingAs($this->student)
        ->post(gnUrl("notifications/{$entry->id}/open"))
        ->assertRedirect('/results/'.$attempt->id);
    expect($entry->fresh()?->read_at)->not->toBeNull();
});

it('opens the result from a grade_changed notification and sends its email in the student\'s locale', function () {
    $this->student->update(['locale' => null]);
    $quiz = gnQuiz(freeText: true);
    $attempt = gnSubmit($quiz, $this->student);
    gnGrade($attempt, '4');
    gnGrade($attempt, '2.5');

    $entry = Notification::query()->where('kind', NotificationKind::GradeChanged->value)->sole();

    $this->actingAs($this->student)
        ->post(gnUrl("notifications/{$entry->id}/open"))
        ->assertRedirect('/results/'.$attempt->id);

    /** @var list<SentMessage> $messages */
    $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->all();
    expect($messages)->toHaveCount(2);

    $email = $messages[1]->getOriginalMessage();
    assert($email instanceof Email);

    expect($email->getSubject())->toBe('Ο βαθμός σας στο Sorting quiz άλλαξε')
        ->and((string) $email->getTextBody())->toContain('http://acme.uplift.test/results/'.$attempt->id)
        ->not->toContain('2.5');
});

describe('Assessment release events (DEC-003)', function () {
    it('dispatches ResultReleased once per attempt and ResultChanged only for regrades of released attempts that change earned_points', function () {
        Event::fake([ResultReleased::class, ResultChanged::class]);
        $quiz = gnQuiz(freeText: true, visibility: ResultVisibility::AfterDeadline, deadline: CarbonImmutable::now()->addHour());
        $attempt = gnSubmit($quiz, $this->student);

        gnGrade($attempt, '4');
        gnGrade($attempt, '3');
        Event::assertNotDispatched(ResultReleased::class);
        Event::assertNotDispatched(ResultChanged::class);

        $this->travel(2)->hours();
        gnRelease();
        gnRelease();

        Event::assertDispatchedTimes(ResultReleased::class, 1);
        Event::assertDispatched(ResultReleased::class, fn (ResultReleased $e): bool => $e->attemptId === $attempt->id && $e->organizationId === $this->acme->id);

        gnGrade($attempt, '3');
        Event::assertNotDispatched(ResultChanged::class);

        gnGrade($attempt, '5');
        Event::assertDispatchedTimes(ResultChanged::class, 1);
        Event::assertDispatched(ResultChanged::class, fn (ResultChanged $e): bool => $e->attemptId === $attempt->id && $e->organizationId === $this->acme->id);
        Event::assertDispatchedTimes(ResultReleased::class, 1);
    });

    it('never revokes a release when the teacher hides the score afterwards', function () {
        NotificationFacade::fake();
        $quiz = gnQuiz();
        $attempt = gnSubmit($quiz, $this->student);
        $released = $attempt->result_released_at;

        $quiz->update(['score_visibility' => ResultVisibility::Hidden]);
        gnRelease();

        expect($attempt->refresh()->result_released_at?->equalTo($released))->toBeTrue()
            ->and(gnCount())->toBe(1);
    });
});

describe('INT-001: EXT-002 unavailable or timing out', function () {
    it('queues the email with timeout 10s and 5 retries with backoff 1m, 5m, 15m, 1h, 6h', function () {
        $quiz = gnQuiz(freeText: true);
        $attempt = gnSubmit($quiz, $this->student);
        Queue::fake();

        gnGrade($attempt);

        expect(Notification::query()->where('user_id', $this->student->id)->count())->toBe(1);
        Queue::assertPushed(SendQueuedNotifications::class, 1);
        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof GradeFinalisedEmail
            && $job->tries === 6
            && $job->timeout === 10
            && $job->backoff() === [60, 300, 900, 3600, 21600]);
    });

    it('keeps the in-app entry and the grade when the mail transport fails', function () {
        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Connection timed out');
            }

            public function __toString(): string
            {
                return 'failing';
            }
        });
        config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);
        Log::spy();

        $attempt = gnSubmit(gnQuiz(), $this->student);

        expect($attempt->status)->toBe(AttemptStatus::Graded)
            ->and(Notification::query()->where('user_id', $this->student->id)->count())->toBe(1);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notifications.email_failed'
            && $context['kind'] === 'grade_finalised'
            && $context['attempt_id'] === $attempt->id
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'x@acme.test'));
    });

    it('does not block the grading and still creates the in-app entry when the queue rejects the email', function () {
        $quiz = gnQuiz(freeText: true);
        $attempt = gnSubmit($quiz, $this->student);
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('queue unavailable'));
        Log::spy();

        gnGrade($attempt);

        expect($attempt->refresh()->status)->toBe(AttemptStatus::Graded)
            ->and(Notification::query()->where('user_id', $this->student->id)->count())->toBe(1);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notifications.email_not_queued'
            && $context['kind'] === 'grade_finalised'
            && $context['user_id'] === $this->student->id
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '@acme.test'));
    });

    it('logs only ids after the last retry', function () {
        Log::spy();

        (new GradeFinalisedEmail($this->student->id, 'attempt-id', 'Algorithms', 'Sorting quiz', 'http://acme.uplift.test/results/1'))
            ->failed(new TransportException('Connection timed out'));

        Log::shouldHaveReceived('warning')->once()->with('notifications.email_failed', [
            'kind' => 'grade_finalised',
            'user_id' => $this->student->id,
            'attempt_id' => 'attempt-id',
            'error' => TransportException::class,
        ]);
    });
});
