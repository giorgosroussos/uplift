<?php

use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\NotificationPreference;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-007 / DEC-002: the notification centre, the bell's unread count and the
| notification email switch; ownership (only one's own notifications and
| preference) and tenant isolation of ENT-Notification and
| ENT-NotificationPreference.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);
    $this->actingAsTenant($this->acme);

    $this->x = User::factory()->student()->create();
    $this->y = User::factory()->student()->create();
});

function centreUrl(string $path = ''): string
{
    return test()->tenantUrl(test()->acme, rtrim('notifications/'.$path, '/'));
}

/**
 * @return array<string, string|null>
 */
function readStates(): array
{
    return Notification::query()->orderBy('id')->get()
        ->mapWithKeys(fn (Notification $notification): array => [$notification->id => $notification->read_at?->toIso8601String()])
        ->all();
}

it('shows the bell with the unread count on every page, and 0 after marking all as read', function () {
    Notification::factory()->for($this->x)->count(3)->create();
    Notification::factory()->for($this->x)->read()->create();
    Notification::factory()->for($this->y)->count(2)->create();

    $this->actingAs($this->x)
        ->get($this->tenantUrl($this->acme, 'dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 3));

    $this->actingAs($this->x)
        ->from(centreUrl())
        ->post(centreUrl('read-all'))
        ->assertRedirect(centreUrl());

    $this->actingAs($this->x)
        ->get(centreUrl())
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0));

    $this->actingAs($this->x)
        ->get($this->tenantUrl($this->acme, 'dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0));

    // Y's notifications are untouched.
    expect(Notification::query()->where('user_id', $this->y->id)->whereNull('read_at')->count())->toBe(2);
});

it('shares no notifications prop with guests', function () {
    $this->get($this->tenantUrl($this->acme, 'login'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications', null));
});

it('lists only the user\'s own notifications, newest first, 20 per page', function () {
    foreach (range(1, 22) as $i) {
        Notification::factory()->for($this->x)->create([
            'created_at' => now()->subHours($i),
            'data' => ['course_space_id' => 's', 'announcement_id' => "a{$i}", 'course_space_title' => 'Algorithms', 'announcement_title' => "Title {$i}"],
        ]);
    }
    Notification::factory()->for($this->y)->create();

    $this->actingAs($this->x)
        ->get(centreUrl())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('entries.data', 20)
            ->where('entries.total', 22)
            ->where('entries.data.0.data.announcement_title', 'Title 1')
            ->where('entries.data.0.kind', 'announcement_published')
            ->where('entries.data.0.read', false)
            ->where('entries.data.19.data.announcement_title', 'Title 20'));

    $this->actingAs($this->x)
        ->get(centreUrl().'?page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 2)
            ->where('entries.data.1.data.announcement_title', 'Title 22'));
});

it('marks one notification read and unread', function () {
    $notification = Notification::factory()->for($this->x)->create();

    $this->actingAs($this->x)->post(centreUrl("{$notification->id}/read"))->assertRedirect();
    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($this->x)->post(centreUrl("{$notification->id}/unread"))->assertRedirect();
    expect($notification->fresh()->read_at)->toBeNull();
});

describe('UC-007: opening a notification', function () {
    beforeEach(function () {
        $this->teacher = User::factory()->teacher()->create();
        $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create(['title' => 'Algorithms']);
        Enrolment::factory()->for($this->space, 'courseSpace')->for($this->x, 'student')->create();

        $this->actingAs($this->teacher)
            ->post($this->tenantUrl($this->acme, "courses/{$this->space->id}/announcements"), ['title' => 'Exam moved', 'body' => 'Room A1'])
            ->assertRedirect();

        $this->announcement = Announcement::query()->sole();
        $this->notification = Notification::query()->where('user_id', $this->x->id)->sole();
    });

    it('marks it read and opens the course space', function () {
        $this->actingAs($this->x)
            ->post(centreUrl("{$this->notification->id}/open"))
            ->assertRedirect('/courses/'.$this->space->id);

        expect($this->notification->fresh()->read_at)->not->toBeNull();

        $this->actingAs($this->x)
            ->get($this->tenantUrl($this->acme, "courses/{$this->space->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('announcements.0.id', $this->announcement->id));
    });

    it('opens the course space without the announcement once it is deleted', function () {
        $this->actingAs($this->teacher)
            ->delete($this->tenantUrl($this->acme, "courses/{$this->space->id}/announcements/{$this->announcement->id}"))
            ->assertRedirect();

        $this->actingAs($this->x)
            ->post(centreUrl("{$this->notification->id}/open"))
            ->assertRedirect('/courses/'.$this->space->id);

        $this->actingAs($this->x)
            ->get($this->tenantUrl($this->acme, "courses/{$this->space->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('announcements', []));
    });

    it('returns 404 once the course space is archived', function () {
        $this->space->transitionTo(CourseSpaceStatus::Archived);

        $this->actingAs($this->x)
            ->post(centreUrl("{$this->notification->id}/open"))
            ->assertNotFound();

        // Still marked read: the user opened it.
        expect($this->notification->fresh()->read_at)->not->toBeNull();
    });

    it('returns 404 once the student is no longer enrolled', function () {
        Enrolment::query()->delete();

        $this->actingAs($this->x)
            ->post(centreUrl("{$this->notification->id}/open"))
            ->assertNotFound();
    });
});

describe('DEC-002 / SEC-004: only one\'s own notifications and preference', function () {
    it('refuses to open or mark another user\'s notification and changes nothing', function (string $action) {
        $ofY = Notification::factory()->for($this->y)->create();
        $readOfY = Notification::factory()->for($this->y)->read()->create();
        $before = readStates();

        foreach ([$ofY, $readOfY] as $notification) {
            $this->actingAs($this->x)
                ->post(centreUrl("{$notification->id}/{$action}"))
                ->assertForbidden();
        }

        expect(readStates())->toBe($before);
    })->with(['open', 'read', 'unread']);

    it('never lists or counts another user\'s notifications', function () {
        Notification::factory()->for($this->y)->count(2)->create();

        $this->actingAs($this->x)
            ->get(centreUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 0)
                ->where('notifications.unread_count', 0));
    });

    it('marks all as read only for the user\'s own notifications', function () {
        Notification::factory()->for($this->y)->count(2)->create();
        $before = readStates();

        $this->actingAs($this->x)->post(centreUrl('read-all'))->assertRedirect();

        expect(readStates())->toBe($before);
    });

    it('changes only the user\'s own notification preference', function () {
        $this->actingAs($this->x)
            ->put($this->tenantUrl($this->acme, 'settings/notifications'), ['email_enabled' => false, 'user_id' => $this->y->id])
            ->assertRedirect($this->tenantUrl($this->acme, 'settings/notifications'));

        expect(NotificationPreference::emailEnabledFor($this->x))->toBeFalse()
            ->and(NotificationPreference::emailEnabledFor($this->y))->toBeTrue()
            ->and(NotificationPreference::query()->where('user_id', $this->y->id)->exists())->toBeFalse();
    });

    it('does not let a user of another organisation reach a notification', function () {
        $ofX = Notification::factory()->for($this->x)->create();
        $outsider = $this->asTenant($this->beta, fn () => User::factory()->student()->create());

        // On their own subdomain the notification of acme does not exist.
        $this->actingAs($outsider)
            ->post($this->tenantUrl($this->beta, "notifications/{$ofX->id}/read"))
            ->assertNotFound();

        // On acme's subdomain the outsider is not let in.
        $response = $this->actingAs($outsider)->post(centreUrl("{$ofX->id}/read"));
        expect($response->status())->toBeIn([302, 403, 404]);

        expect($ofX->fresh()->read_at)->toBeNull();
    });
});

describe('DEC-002: notification email switch', function () {
    it('shows the switch, on by default', function () {
        $this->actingAs($this->x)
            ->get($this->tenantUrl($this->acme, 'settings/notifications'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Settings')
                ->where('emailEnabled', true));
    });

    it('turns notification emails off and on again', function () {
        foreach ([false, true] as $enabled) {
            $this->actingAs($this->x)
                ->put($this->tenantUrl($this->acme, 'settings/notifications'), ['email_enabled' => $enabled])
                ->assertRedirect();

            $this->actingAs($this->x)
                ->get($this->tenantUrl($this->acme, 'settings/notifications'))
                ->assertInertia(fn (Assert $page) => $page->where('emailEnabled', $enabled));
        }

        expect(NotificationPreference::query()->where('user_id', $this->x->id)->count())->toBe(1);
    });

    it('rejects a missing or invalid value', function () {
        $this->actingAs($this->x)
            ->put($this->tenantUrl($this->acme, 'settings/notifications'), ['email_enabled' => 'maybe'])
            ->assertSessionHasErrors('email_enabled');

        expect(NotificationPreference::query()->count())->toBe(0);
    });

    it('requires a signed-in user', function () {
        $this->get($this->tenantUrl($this->acme, 'settings/notifications'))->assertRedirect();
        $this->get(centreUrl())->assertRedirect();
    });
});

describe('ENT-Notification retention and tenant isolation', function () {
    it('prunes notifications older than 180 days and keeps younger ones', function () {
        $old = Notification::factory()->for($this->x)->create(['created_at' => now()->subDays(181)]);
        $young = Notification::factory()->for($this->x)->create(['created_at' => now()->subDays(179)]);
        $oldOfBeta = $this->asTenant($this->beta, fn () => Notification::factory()
            ->for(User::factory()->student()->create())
            ->create(['created_at' => now()->subDays(200)]));

        $this->withoutTenant();
        $this->artisan('model:prune', ['--model' => [Notification::class]])->assertSuccessful();
        $this->actingAsTenant($this->acme);

        expect(Notification::query()->pluck('id')->all())->toBe([$young->id])
            ->and(Notification::query()->withoutGlobalScopes()->whereKey([$old->id, $oldOfBeta->id])->exists())->toBeFalse();
    });

    it('schedules the daily retention prune', function () {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'model:prune')
                && str_contains((string) $event->command, 'Notification'));

        expect($events)->toHaveCount(1)
            ->and($events->first()->expression)->toBe('0 0 * * *');
    });

    it('isolates notifications per organisation', function () {
        $this->assertTenantIsolated(Notification::class, fn () => Notification::factory()
            ->for(User::factory()->student()->create())
            ->create());
    });

    it('isolates notification preferences per organisation', function () {
        $this->assertTenantIsolated(NotificationPreference::class, fn () => NotificationPreference::query()->create([
            'user_id' => User::factory()->student()->create()->id,
            'email_enabled' => false,
        ]));
    });
});
