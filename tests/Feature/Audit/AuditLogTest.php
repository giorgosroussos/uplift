<?php

use App\Modules\Audit\Actions\AuditRecord;
use App\Modules\Audit\Actions\RecordAuditEntry;
use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Audit\Enums\ActorType;
use App\Modules\Audit\Listeners\AuditListener;
use App\Modules\Audit\Models\AppendOnlyViolation;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Tenancy\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/*
| P1-T5: ENT-AuditEntry and the audit listener infrastructure (SEC-010).
*/

uses(RefreshDatabase::class);

final class AuditLogTestEvent
{
    public function __construct(public string $organizationId, public string $subjectId, public bool $audited = true) {}
}

final class AuditLogTestListener extends AuditListener
{
    protected function toRecord(object $event): ?AuditRecord
    {
        assert($event instanceof AuditLogTestEvent);

        if (! $event->audited) {
            return null;
        }

        return new AuditRecord(
            action: 'test.happened',
            actorType: ActorType::System,
            actorId: null,
            subjectType: 'test_subject',
            subjectId: $event->subjectId,
            organizationId: $event->organizationId,
        );
    }
}

function auditRecord(array $overrides = []): AuditRecord
{
    return new AuditRecord(...array_merge([
        'action' => 'user.role_changed',
        'actorType' => ActorType::User,
        'actorId' => (string) Str::uuid(),
        'subjectType' => 'user',
        'subjectId' => (string) Str::uuid(),
        'organizationId' => array_key_exists('organizationId', $overrides) ? $overrides['organizationId'] : Organization::factory()->create()->id,
        'changes' => ['role' => ['from' => 'student', 'to' => 'teacher']],
    ], $overrides));
}

it('stores audit entries with the ENT-AuditEntry columns and storage types', function () {
    $columns = collect(DB::select(
        "select column_name, data_type, is_nullable from information_schema.columns where table_name = 'audit_entries'"
    ))->keyBy('column_name');

    expect($columns->keys()->sort()->values()->all())->toBe([
        'action', 'actor_id', 'actor_type', 'changes', 'id', 'occurred_at', 'organization_id', 'subject_id', 'subject_type',
    ])
        ->and($columns['id']->data_type)->toBe('uuid')
        ->and($columns['organization_id']->data_type)->toBe('uuid')
        ->and($columns['organization_id']->is_nullable)->toBe('YES')
        ->and($columns['actor_id']->data_type)->toBe('uuid')
        ->and($columns['actor_id']->is_nullable)->toBe('YES')
        ->and($columns['subject_id']->data_type)->toBe('uuid')
        ->and($columns['subject_id']->is_nullable)->toBe('NO')
        ->and($columns['actor_type']->is_nullable)->toBe('NO')
        ->and($columns['action']->is_nullable)->toBe('NO')
        ->and($columns['subject_type']->is_nullable)->toBe('NO')
        ->and($columns['changes']->data_type)->toBe('jsonb')
        ->and($columns['occurred_at']->data_type)->toBe('timestamp with time zone')
        ->and($columns['occurred_at']->is_nullable)->toBe('NO');
});

it('records an entry with a UUID primary key', function () {
    $record = auditRecord();

    $entry = app(RecordAuditEntry::class)->handle($record);
    $stored = AuditEntry::query()->findOrFail($entry->id);

    expect(Str::isUuid($entry->id))->toBeTrue()
        ->and($stored->action)->toBe('user.role_changed')
        ->and($stored->actor_type)->toBe(ActorType::User)
        ->and($stored->actor_id)->toBe($record->actorId)
        ->and($stored->subject_type)->toBe('user')
        ->and($stored->subject_id)->toBe($record->subjectId)
        ->and($stored->organization_id)->toBe($record->organizationId)
        ->and($stored->changes)->toEqual(['role' => ['from' => 'student', 'to' => 'teacher']]);
});

it('allows entries without an organisation or an actor (system and platform level)', function () {
    $entry = app(RecordAuditEntry::class)->handle(auditRecord([
        'actorType' => ActorType::System,
        'actorId' => null,
        'organizationId' => null,
        'changes' => null,
    ]));

    expect($entry->fresh()?->organization_id)->toBeNull()
        ->and($entry->fresh()?->actor_id)->toBeNull()
        ->and($entry->fresh()?->changes)->toBeNull();
});

it('stores occurred_at in UTC', function () {
    $athens = CarbonImmutable::parse('2026-07-01 15:00:00', 'Europe/Athens');

    $entry = app(RecordAuditEntry::class)->handle(auditRecord(['occurredAt' => $athens]));

    $raw = DB::selectOne("select to_char(occurred_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI:SS') as utc from audit_entries where id = ?", [$entry->id]);

    expect($raw->utc)->toBe('2026-07-01 12:00:00')
        ->and($entry->fresh()?->occurred_at->toDateTimeString())->toBe('2026-07-01 12:00:00');
});

it('defaults occurred_at to now', function () {
    CarbonImmutable::setTestNow('2026-10-19 08:30:00');

    $entry = app(RecordAuditEntry::class)->handle(auditRecord());

    expect($entry->fresh()?->occurred_at->toDateTimeString())->toBe('2026-10-19 08:30:00');

    CarbonImmutable::setTestNow();
});

it('refuses to update an entry and leaves it unchanged', function () {
    $entry = app(RecordAuditEntry::class)->handle(auditRecord());

    expect(fn () => $entry->update(['action' => 'tampered']))->toThrow(AppendOnlyViolation::class)
        ->and(AuditEntry::query()->findOrFail($entry->id)->action)->toBe('user.role_changed');
});

it('refuses to delete an entry and keeps it', function () {
    $entry = app(RecordAuditEntry::class)->handle(auditRecord());

    expect(fn () => $entry->delete())->toThrow(AppendOnlyViolation::class)
        ->and(AuditEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('rejects an actor_type outside user, platform_admin and system at the database', function () {
    $insert = fn () => DB::transaction(fn () => DB::table('audit_entries')->insert([
        'id' => (string) Str::uuid(),
        'actor_type' => 'visitor',
        'action' => 'x',
        'subject_type' => 'y',
        'subject_id' => (string) Str::uuid(),
        'occurred_at' => now(),
    ]));

    expect($insert)->toThrow(QueryException::class)
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('prunes entries older than 2 years and keeps younger ones', function () {
    CarbonImmutable::setTestNow('2026-10-19 12:00:00');
    $record = app(RecordAuditEntry::class);

    $old = $record->handle(auditRecord(['occurredAt' => CarbonImmutable::parse('2024-10-19 11:59:59')]));
    $young = $record->handle(auditRecord(['occurredAt' => CarbonImmutable::parse('2024-10-19 12:00:01')]));

    $this->artisan('model:prune', ['--model' => [AuditEntry::class]])->assertSuccessful();

    expect(AuditEntry::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AuditEntry::query()->whereKey($young->id)->exists())->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('schedules the daily retention prune of audit entries', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'model:prune')
            && str_contains((string) $event->command, 'AuditEntry'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *');
});

it('writes an entry when an event with an audit listener is dispatched', function () {
    Event::listen(AuditLogTestEvent::class, AuditLogTestListener::class);
    $organizationId = Organization::factory()->create()->id;
    $subjectId = (string) Str::uuid();

    event(new AuditLogTestEvent($organizationId, $subjectId));

    $entry = AuditEntry::query()->sole();

    expect($entry->action)->toBe('test.happened')
        ->and($entry->actor_type)->toBe(ActorType::System)
        ->and($entry->organization_id)->toBe($organizationId)
        ->and($entry->subject_id)->toBe($subjectId);
});

it('writes nothing when the audit listener maps the event to no entry', function () {
    Event::listen(AuditLogTestEvent::class, AuditLogTestListener::class);

    event(new AuditLogTestEvent(Organization::factory()->create()->id, (string) Str::uuid(), audited: false));

    expect(AuditEntry::query()->count())->toBe(0);
});

it('registers only audit listeners in the Audit module', function () {
    $provider = app()->getProvider(AuditServiceProvider::class);

    foreach ($provider->listeners() as $listeners) {
        foreach ($listeners as $listener) {
            expect(is_subclass_of($listener, AuditListener::class))->toBeTrue("{$listener} must extend AuditListener");
        }
    }
});
