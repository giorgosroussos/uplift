<?php

use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Notifications\Actions\UpdateNotificationPreference;
use App\Modules\Notifications\Enums\NotificationKind;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Notifications\AnnouncementPublishedEmail;
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
| UC-007 / DEC-001 / DEC-002 / INT-001: a published announcement gives every
| enrolled student one in-app notification and, unless they turned
| notification emails off, one queued email in their locale.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'default_locale' => 'el']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
    $this->x = User::factory()->student()->create(['email' => 'x@acme.test', 'name' => 'Xena Student', 'locale' => 'en']);
    $this->y = User::factory()->student()->create(['email' => 'y@acme.test', 'name' => 'Yannis Student', 'locale' => null]);
    $this->notEnrolled = User::factory()->student()->create();

    $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);

    foreach ([$this->x, $this->y] as $student) {
        Enrolment::factory()->for($this->space, 'courseSpace')->for($student, 'student')->create();
    }
});

function postAnnouncement(string $title = 'Exam moved', string $body = 'Secret body: room A1'): void
{
    $test = test();
    $test->actingAs($test->teacher)
        ->post($test->tenantUrl($test->acme, "courses/{$test->space->id}/announcements"), ['title' => $title, 'body' => $body])
        ->assertRedirect($test->tenantUrl($test->acme, "courses/{$test->space->id}"))
        ->assertSessionHasNoErrors();
}

/**
 * @return Collection<int, Email>
 */
function arrayMailerMessages(): Collection
{
    /** @var Collection<int, SentMessage> $messages */
    $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();

    return $messages->map(function (SentMessage $message): Email {
        $original = $message->getOriginalMessage();
        assert($original instanceof Email);

        return $original;
    })->values();
}

it('gives every enrolled student one in-app entry and one queued email in their locale, and the author none', function () {
    NotificationFacade::fake();

    postAnnouncement();

    $announcement = Announcement::query()->sole();

    foreach ([$this->x, $this->y] as $student) {
        $entry = Notification::query()->where('user_id', $student->id)->sole();

        expect($entry->kind)->toBe(NotificationKind::AnnouncementPublished)
            ->and($entry->organization_id)->toBe($this->acme->id)
            ->and($entry->read_at)->toBeNull()
            // DEC-002: ids and title snapshots only, never the body or personal data.
            ->and($entry->data)->toEqual([
                'course_space_id' => $this->space->id,
                'announcement_id' => $announcement->id,
                'course_space_title' => 'Algorithms',
                'announcement_title' => 'Exam moved',
            ]);
    }

    expect(Notification::query()->where('user_id', $this->teacher->id)->exists())->toBeFalse()
        ->and(Notification::query()->where('user_id', $this->notEnrolled->id)->exists())->toBeFalse()
        ->and(Notification::query()->count())->toBe(2);

    expect(new AnnouncementPublishedEmail('u', 'a', 's', 't', 'l'))->toBeInstanceOf(ShouldQueue::class);

    NotificationFacade::assertSentOnDemandTimes(AnnouncementPublishedEmail::class, 2);

    foreach ([[$this->x, 'en'], [$this->y, 'el']] as [$student, $locale]) {
        NotificationFacade::assertSentOnDemand(
            AnnouncementPublishedEmail::class,
            fn (AnnouncementPublishedEmail $email, array $channels, AnonymousNotifiable $notifiable, ?string $sentLocale): bool => $notifiable->routes['mail'] === $student->email
                && $sentLocale === $locale
                && $channels === ['mail']
                && $email->userId === $student->id
                && $email->url === 'http://acme.uplift.test/courses/'.$this->space->id,
        );
    }

    NotificationFacade::assertNotSentTo($this->teacher, AnnouncementPublishedEmail::class);
});

it('sends the email with the space name, the title and the link but not the body, in the user locale else the organisation default', function () {
    postAnnouncement('Exam moved', 'Secret body: room A1');

    $messages = arrayMailerMessages();
    expect($messages)->toHaveCount(2);

    $to = fn (Email $email): string => $email->getTo()[0]->getAddress();
    $forX = $messages->first(fn (Email $email): bool => $to($email) === 'x@acme.test');
    $forY = $messages->first(fn (Email $email): bool => $to($email) === 'y@acme.test');
    $link = 'http://acme.uplift.test/courses/'.$this->space->id;

    // X chose English; Y has no locale, so the organisation's Greek default.
    expect($forX->getSubject())->toBe('New announcement in Algorithms')
        ->and($forY->getSubject())->toBe('Νέα ανακοίνωση στο Algorithms');

    foreach ([$forX, $forY] as $email) {
        $text = (string) $email->getTextBody();

        expect($text)->toContain('Algorithms')
            ->toContain('Exam moved')
            ->toContain($link)
            ->not->toContain('Secret body')
            ->and((string) $email->getHtmlBody())->not->toContain('Secret body');
    }

    expect((string) $forX->getTextBody())->toContain('A new announcement was posted in Algorithms.')
        ->and((string) $forY->getTextBody())->toContain('Αναρτήθηκε νέα ανακοίνωση στο Algorithms.');
});

it('keeps the in-app entry when the mail transport fails for a student, and does not block the post', function () {
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

    postAnnouncement();

    expect(Notification::query()->where('user_id', $this->x->id)->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $this->y->id)->count())->toBe(1)
        ->and($sent->getArrayCopy())->toBe([['y@acme.test']]);

    // ARCH-006: the failure is logged with ids only, never the email address.
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notifications.email_failed'
        && $context['user_id'] === $this->x->id
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'x@acme.test'));
});

it('sends no notification email to a student who turned them off, but still the in-app entry and account emails', function () {
    $this->actingAs($this->x)
        ->put($this->tenantUrl($this->acme, 'settings/notifications'), ['email_enabled' => false])
        ->assertRedirect();

    NotificationFacade::fake();

    postAnnouncement();

    expect(Notification::query()->where('user_id', $this->x->id)->count())->toBe(1);

    NotificationFacade::assertSentOnDemandTimes(AnnouncementPublishedEmail::class, 1);
    NotificationFacade::assertSentOnDemand(
        AnnouncementPublishedEmail::class,
        fn (AnnouncementPublishedEmail $email, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'y@acme.test',
    );

    // Account emails cannot be turned off (DEC-002).
    auth()->logout();
    $this->post($this->tenantUrl($this->acme, 'forgot-password'), ['email' => 'x@acme.test']);

    NotificationFacade::assertSentTo($this->x, ResetPasswordNotification::class);
});

it('sends the email again once the student turns notification emails back on', function () {
    app(UpdateNotificationPreference::class)->handle($this->x, false);
    app(UpdateNotificationPreference::class)->handle($this->x, true);

    NotificationFacade::fake();

    postAnnouncement();

    NotificationFacade::assertSentOnDemandTimes(AnnouncementPublishedEmail::class, 2);
});

it('notifies nobody for a post in an archived course space', function () {
    NotificationFacade::fake();
    $this->space->transitionTo(CourseSpaceStatus::Archived);

    postAnnouncement();

    expect(Notification::query()->count())->toBe(0);
    NotificationFacade::assertNothingSent();
});

it('keeps the title snapshot when the announcement is edited later', function () {
    postAnnouncement('Exam moved');

    $this->actingAs($this->teacher)
        ->put($this->tenantUrl($this->acme, 'courses/'.$this->space->id.'/announcements/'.Announcement::query()->sole()->id), ['title' => 'Exam cancelled', 'body' => 'x'])
        ->assertRedirect();

    expect(Notification::query()->where('user_id', $this->x->id)->sole()->data['announcement_title'])->toBe('Exam moved');
});

describe('INT-001: EXT-002 unavailable or timing out', function () {
    it('queues the emails with timeout 10s and 5 retries with backoff 1m, 5m, 15m, 1h, 6h', function () {
        Queue::fake();

        postAnnouncement();

        // The in-app entries exist although no email has been sent yet.
        expect(Notification::query()->count())->toBe(2);

        Queue::assertPushed(SendQueuedNotifications::class, 2);
        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof AnnouncementPublishedEmail
            && $job->tries === 6
            && $job->timeout === 10
            && $job->backoff() === [60, 300, 900, 3600, 21600]);
    });

    it('does not block the post and still creates the in-app entries when the queue rejects the emails', function () {
        Queue::shouldReceive('connection')->andThrow(new RuntimeException('queue unavailable'));
        Log::spy();

        postAnnouncement();

        expect(Notification::query()->count())->toBe(2)
            ->and(Announcement::query()->count())->toBe(1);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notifications.email_not_queued'
            && in_array($context['user_id'], [$this->x->id, $this->y->id], true)
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '@acme.test'));
    });

    it('logs only ids after the last retry', function () {
        Log::spy();

        (new AnnouncementPublishedEmail($this->x->id, 'announcement-id', 'Algorithms', 'Exam moved', 'http://acme.uplift.test/courses/1'))
            ->failed(new TransportException('Connection timed out'));

        Log::shouldHaveReceived('warning')->once()->with('notifications.email_failed', [
            'kind' => 'announcement_published',
            'user_id' => $this->x->id,
            'announcement_id' => 'announcement-id',
            'error' => TransportException::class,
        ]);
    });
});
