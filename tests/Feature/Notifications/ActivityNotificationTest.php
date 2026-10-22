<?php

use App\Modules\Activities\Enums\ActivityStatus;
use App\Modules\Activities\Models\Activity;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Actions\UpdateNotificationPreference;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Notifications\ActivityPublishedEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/*
| DEC-001 / INT-001: a published activity gives every student enrolled in
| its course space one in-app activity_published notification and, unless
| they turned notification emails off, one queued email in their locale.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'default_locale' => 'el']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->x = User::factory()->student()->create(['email' => 'x@acme.test', 'locale' => 'en']);
    $this->y = User::factory()->student()->create(['email' => 'y@acme.test', 'locale' => null]);
    $this->notEnrolled = User::factory()->student()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
    $this->otherSpace = CourseSpace::factory()->for($this->teacher, 'teacher')->create();

    foreach ([$this->x, $this->y] as $student) {
        Enrolment::factory()->for($this->space, 'courseSpace')->for($student, 'student')->create();
    }
    Enrolment::factory()->for($this->otherSpace, 'courseSpace')->for($this->notEnrolled, 'student')->create();

    $this->activity = Activity::factory()->for($this->space, 'courseSpace')->create([
        'title' => 'Sorting quiz',
        'description' => 'Secret description',
    ]);
    Quiz::factory()->for($this->activity, 'activity')->withQuestions(1, Question::factory())->create();
});

function publishActivity(?Activity $activity = null): void
{
    $test = test();
    $activity ??= $test->activity;

    $test->actingAs($test->teacher)
        ->post($test->tenantUrl($test->acme, "activities/{$activity->id}/publish"))
        ->assertRedirect($test->tenantUrl($test->acme, "activities/{$activity->id}"))
        ->assertSessionHasNoErrors();

    expect($activity->fresh()?->status)->toBe(ActivityStatus::Published);
}

/**
 * @return Collection<int, Email>
 */
function activityMails(): Collection
{
    /** @var Collection<int, SentMessage> $messages */
    $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();

    return $messages->map(function (SentMessage $message): Email {
        $original = $message->getOriginalMessage();
        assert($original instanceof Email);

        return $original;
    })->values();
}

it('gives every enrolled student one in-app entry and one queued email in their locale, and the teacher and others none', function () {
    NotificationFacade::fake();

    publishActivity();

    foreach ([$this->x, $this->y] as $student) {
        $entry = Notification::query()->where('user_id', $student->id)->sole();

        expect($entry->kind)->toBe(NotificationKind::ActivityPublished)
            ->and($entry->organization_id)->toBe($this->acme->id)
            ->and($entry->read_at)->toBeNull()
            // Ids and title snapshots only, never the description.
            ->and($entry->data)->toEqual([
                'course_space_id' => $this->space->id,
                'activity_id' => $this->activity->id,
                'course_space_title' => 'Algorithms',
                'activity_title' => 'Sorting quiz',
            ]);
    }

    expect(Notification::query()->where('user_id', $this->teacher->id)->exists())->toBeFalse()
        ->and(Notification::query()->where('user_id', $this->notEnrolled->id)->exists())->toBeFalse()
        ->and(Notification::query()->count())->toBe(2);

    expect(new ActivityPublishedEmail('u', 'a', 's', 't', 'l'))->toBeInstanceOf(ShouldQueue::class);

    NotificationFacade::assertSentOnDemandTimes(ActivityPublishedEmail::class, 2);

    foreach ([[$this->x, 'en'], [$this->y, 'el']] as [$student, $locale]) {
        NotificationFacade::assertSentOnDemand(
            ActivityPublishedEmail::class,
            fn (ActivityPublishedEmail $email, array $channels, AnonymousNotifiable $notifiable, ?string $sentLocale): bool => $notifiable->routes['mail'] === $student->email
                && $sentLocale === $locale
                && $channels === ['mail']
                && $email->userId === $student->id
                && $email->url === 'http://acme.uplift.test/activities/'.$this->activity->id,
        );
    }
});

it('sends the email with the space name, the activity title and the link but not the description', function () {
    publishActivity();

    $messages = activityMails();
    expect($messages)->toHaveCount(2);

    $to = fn (Email $email): string => $email->getTo()[0]->getAddress();
    $forX = $messages->first(fn (Email $email): bool => $to($email) === 'x@acme.test');
    $forY = $messages->first(fn (Email $email): bool => $to($email) === 'y@acme.test');

    expect($forX->getSubject())->toBe('New activity in Algorithms')
        ->and($forY->getSubject())->toBe('Νέα δραστηριότητα στο Algorithms');

    foreach ([$forX, $forY] as $email) {
        expect((string) $email->getTextBody())->toContain('Sorting quiz')
            ->toContain('http://acme.uplift.test/activities/'.$this->activity->id)
            ->not->toContain('Secret description')
            ->and((string) $email->getHtmlBody())->not->toContain('Secret description');
    }
});

it('notifies nobody when publishing is refused', function () {
    NotificationFacade::fake();
    $this->activity->update(['title' => '']);

    $this->actingAs($this->teacher)
        ->post($this->tenantUrl($this->acme, "activities/{$this->activity->id}/publish"))
        ->assertSessionHasErrors('title');

    expect(Notification::query()->count())->toBe(0);
    NotificationFacade::assertNothingSent();
});

it('sends no email to a student who turned notification emails off, but still the in-app entry', function () {
    app(UpdateNotificationPreference::class)->handle($this->x, false);
    NotificationFacade::fake();

    publishActivity();

    expect(Notification::query()->where('user_id', $this->x->id)->count())->toBe(1);
    NotificationFacade::assertSentOnDemandTimes(ActivityPublishedEmail::class, 1);
    NotificationFacade::assertSentOnDemand(
        ActivityPublishedEmail::class,
        fn (ActivityPublishedEmail $email, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'y@acme.test',
    );
});

it('opens the activity from the notification while the student may view it, and 404 once unenrolled or deleted', function () {
    NotificationFacade::fake();
    publishActivity();

    $forX = Notification::query()->where('user_id', $this->x->id)->sole();
    $forY = Notification::query()->where('user_id', $this->y->id)->sole();

    $this->actingAs($this->x)
        ->post($this->tenantUrl($this->acme, "notifications/{$forX->id}/open"))
        ->assertRedirect('/activities/'.$this->activity->id);
    expect($forX->fresh()?->read_at)->not->toBeNull();

    // Another user's notification cannot be opened.
    $this->actingAs($this->x)
        ->post($this->tenantUrl($this->acme, "notifications/{$forY->id}/open"))
        ->assertForbidden();
    expect($forY->fresh()?->read_at)->toBeNull();

    Enrolment::query()->where('student_id', $this->x->id)->delete();
    $this->actingAs($this->x)
        ->post($this->tenantUrl($this->acme, "notifications/{$forX->id}/open"))
        ->assertNotFound();

    $this->activity->delete();
    $this->actingAs($this->y)
        ->post($this->tenantUrl($this->acme, "notifications/{$forY->id}/open"))
        ->assertNotFound();
});

describe('INT-001: EXT-002 unavailable or timing out', function () {
    it('queues the emails with timeout 10s and 5 retries with backoff 1m, 5m, 15m, 1h, 6h', function () {
        Queue::fake();

        publishActivity();

        // The in-app entries exist although no email has been sent yet.
        expect(Notification::query()->count())->toBe(2);

        Queue::assertPushed(SendQueuedNotifications::class, 2);
        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof ActivityPublishedEmail
            && $job->tries === 6
            && $job->timeout === 10
            && $job->backoff() === [60, 300, 900, 3600, 21600]);
    });

    it('keeps the in-app entry when the mail transport fails for a student, and does not block the publish', function () {
        $sent = new ArrayObject;

        Mail::extend('failing-for-x', fn () => new class($sent) extends AbstractTransport
        {
            public function __construct(private ArrayObject $sent)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                $recipients = array_map(fn (Address $address): string => $address->getAddress(), $message->getEnvelope()->getRecipients());

                if (in_array('x@acme.test', $recipients, true)) {
                    throw new TransportException('Connection timed out');
                }

                $this->sent->append($recipients);
            }

            public function __toString(): string
            {
                return 'failing-for-x';
            }
        });
        config(['mail.mailers.failing-for-x' => ['transport' => 'failing-for-x'], 'mail.default' => 'failing-for-x']);
        Log::spy();

        publishActivity();

        expect(Notification::query()->where('user_id', $this->x->id)->count())->toBe(1)
            ->and(Notification::query()->where('user_id', $this->y->id)->count())->toBe(1)
            ->and($sent->getArrayCopy())->toBe([['y@acme.test']]);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notifications.email_failed'
            && $context['kind'] === 'activity_published'
            && $context['user_id'] === $this->x->id
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'x@acme.test'));
    });

    it('does not block the publish and still creates the in-app entries when the queue rejects the emails', function () {
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('queue unavailable'));
        Log::spy();

        publishActivity();

        expect(Notification::query()->count())->toBe(2);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notifications.email_not_queued'
            && $context['kind'] === 'activity_published'
            && $context['activity_id'] === $this->activity->id
            && in_array($context['user_id'], [$this->x->id, $this->y->id], true)
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '@acme.test'));
    });

    it('logs only ids after the last retry', function () {
        Log::spy();

        (new ActivityPublishedEmail($this->x->id, 'activity-id', 'Algorithms', 'Sorting quiz', 'http://acme.uplift.test/activities/1'))
            ->failed(new TransportException('Connection timed out'));

        Log::shouldHaveReceived('warning')->once()->with('notifications.email_failed', [
            'kind' => 'activity_published',
            'user_id' => $this->x->id,
            'activity_id' => 'activity-id',
            'error' => TransportException::class,
        ]);
    });
});
