<?php

use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Enums\AttemptStatus;
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
use App\Modules\Notifications\Notifications\GradeFinalisedEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

/*
| DEC-001 / INT-001: when a quiz attempt gets its final grade (GradeFinalised),
| its student gets one in-app grade_finalised notification and, unless they
| turned notification emails off, one queued email in their locale; neither
| carries the score (SEC-003).
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
 * A published self-paced quiz "Sorting quiz" with one question of the given kind.
 */
function gnQuiz(bool $freeText = false): Quiz
{
    $question = $freeText ? Question::factory()->freeText() : Question::factory();

    return Quiz::factory()
        ->for(Activity::factory()->for(test()->space, 'courseSpace')->published()->create(['title' => 'Sorting quiz']), 'activity')
        ->withQuestions(1, $question->state(['points' => '5.00']))
        ->create();
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

it('gives the student one in-app entry and one queued email when an auto-graded attempt is final at submission', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz();

    $attempt = gnSubmit($quiz, $this->student);
    expect($attempt->status)->toBe(AttemptStatus::Graded);

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

it('notifies only when the last pending answer is graded, once, and never the teacher or a classmate', function () {
    NotificationFacade::fake();
    $quiz = gnQuiz(freeText: true);

    $attempt = gnSubmit($quiz, $this->student);
    gnSubmit($quiz, $this->classmate);

    // Provisional: nothing to tell yet.
    expect($attempt->status)->toBe(AttemptStatus::Submitted)
        ->and(Notification::query()->count())->toBe(0);
    NotificationFacade::assertNothingSent();

    gnGrade($attempt);

    expect(Notification::query()->where('user_id', $this->student->id)->count())->toBe(1)
        ->and(Notification::query()->count())->toBe(1);

    // A regrade of a final attempt is no new finalisation.
    gnGrade($attempt, '5');

    expect(Notification::query()->count())->toBe(1);
    NotificationFacade::assertSentOnDemandTimes(GradeFinalisedEmail::class, 1);
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
