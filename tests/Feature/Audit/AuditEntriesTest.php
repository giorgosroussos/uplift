<?php

use App\Modules\Audit\Enums\ActorType;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
| P2-T7: audit entries for organisation registration, role changes, user
| deactivation, organisation settings changes and course space archiving
| (SEC-010), and the foreign key audit_entries.organization_id.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test', 'app.url' => 'http://uplift.test']);
    App::setLocale('en');
    Notification::fake();
});

/**
 * A complete valid organisation settings form, with overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function auditSettingsForm(Organization $organization, array $overrides = []): array
{
    return [
        'accent_color' => $organization->accent_color ?? '',
        'student_term_el' => $organization->student_term_el,
        'student_term_en' => $organization->student_term_en,
        'teacher_term_el' => $organization->teacher_term_el,
        'teacher_term_en' => $organization->teacher_term_en,
        'grading_scale' => $organization->grading_scale->value,
        'leaderboard_enabled' => $organization->leaderboard_enabled ? '1' : '0',
        'default_locale' => $organization->default_locale->value,
        ...$overrides,
    ];
}

describe('organization.registered', function () {
    it('writes one entry by the new admin on the organisation with only the subdomain', function () {
        $this->post($this->apexUrl('register'), [
            'organization_name' => 'Acme',
            'subdomain' => 'acme',
            'name' => 'Ada Admin',
            'email' => 'ada@acme.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasNoErrors();

        $organization = Organization::query()->sole();
        $admin = User::query()->withoutGlobalScopes()->sole();
        $entry = AuditEntry::query()->sole();

        expect($entry->action)->toBe('organization.registered')
            ->and($entry->actor_type)->toBe(ActorType::User)
            ->and($entry->actor_id)->toBe($admin->getKey())
            ->and($entry->subject_type)->toBe('organization')
            ->and($entry->subject_id)->toBe($organization->getKey())
            ->and($entry->organization_id)->toBe($organization->getKey())
            ->and($entry->changes)->toBe(['subdomain' => 'acme']);
    });

    it('writes no entry when the registration is rejected', function () {
        $this->createOrganization(['subdomain' => 'acme']);

        $this->post($this->apexUrl('register'), [
            'organization_name' => 'Acme',
            'subdomain' => 'acme',
            'name' => 'Ada Admin',
            'email' => 'ada@acme.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertSessionHasErrors('subdomain');

        expect(AuditEntry::query()->count())->toBe(0);
    });
});

describe('user role changes and deactivation', function () {
    beforeEach(function () {
        $this->acme = $this->createOrganization(['subdomain' => 'acme']);
        $acmeUsers = User::factory()->for($this->acme, 'organization');
        $this->admin = $acmeUsers->orgAdmin()->create();
        $this->student = $acmeUsers->student()->create();
    });

    it('writes one user.role_changed entry with the role from and to', function () {
        $this->actingAs($this->admin)
            ->patch($this->tenantUrl($this->acme, "users/{$this->student->getKey()}/role"), ['role' => 'teacher'])
            ->assertSessionHasNoErrors();

        $entry = AuditEntry::query()->sole();

        expect($entry->action)->toBe('user.role_changed')
            ->and($entry->actor_type)->toBe(ActorType::User)
            ->and($entry->actor_id)->toBe($this->admin->getKey())
            ->and($entry->subject_type)->toBe('user')
            ->and($entry->subject_id)->toBe($this->student->getKey())
            ->and($entry->organization_id)->toBe($this->acme->getKey())
            ->and($entry->changes)->toEqual(['role' => ['from' => 'student', 'to' => 'teacher']]);
    });

    it('writes no entry when a role change is rejected', function () {
        $this->actingAs($this->admin)
            ->patch($this->tenantUrl($this->acme, "users/{$this->admin->getKey()}/role"), ['role' => 'teacher'])
            ->assertSessionHasErrors('role');

        expect($this->admin->fresh()->role)->toBe(Role::OrgAdmin)
            ->and(AuditEntry::query()->count())->toBe(0);
    });

    it('writes no entry when a non-admin tries to change a role', function () {
        $teacher = User::factory()->for($this->acme, 'organization')->teacher()->create();

        $this->actingAs($teacher)
            ->patch($this->tenantUrl($this->acme, "users/{$this->student->getKey()}/role"), ['role' => 'teacher'])
            ->assertForbidden();

        expect(AuditEntry::query()->count())->toBe(0);
    });

    it('writes one user.deactivated entry and none for the reactivation', function () {
        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, "users/{$this->student->getKey()}/deactivate"))
            ->assertSessionHasNoErrors();

        $entry = AuditEntry::query()->sole();

        expect($entry->action)->toBe('user.deactivated')
            ->and($entry->actor_type)->toBe(ActorType::User)
            ->and($entry->actor_id)->toBe($this->admin->getKey())
            ->and($entry->subject_type)->toBe('user')
            ->and($entry->subject_id)->toBe($this->student->getKey())
            ->and($entry->organization_id)->toBe($this->acme->getKey())
            ->and($entry->changes)->toBeNull();

        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, "users/{$this->student->getKey()}/reactivate"))
            ->assertSessionHasNoErrors();

        expect(AuditEntry::query()->count())->toBe(1);
    });

    it('writes no entry when deactivating the last active org admin is rejected', function () {
        $this->actingAs($this->admin)
            ->post($this->tenantUrl($this->acme, "users/{$this->admin->getKey()}/deactivate"));

        expect($this->admin->fresh()->is_active)->toBeTrue()
            ->and(AuditEntry::query()->count())->toBe(0);
    });
});

describe('organization.settings_changed', function () {
    beforeEach(function () {
        $this->acme = $this->createOrganization([
            'subdomain' => 'acme',
            'accent_color' => null,
            'leaderboard_enabled' => true,
            'default_locale' => Locale::English,
        ]);
        $this->admin = User::factory()->for($this->acme, 'organization')->orgAdmin()->create();
        $this->url = $this->tenantUrl($this->acme, 'organization/settings');
    });

    it('writes one entry per save with the changes as dispatched', function () {
        $this->actingAs($this->admin)->from($this->url)->post($this->url, auditSettingsForm($this->acme, [
            'accent_color' => '#1A2B3C',
            'leaderboard_enabled' => '0',
        ]))->assertSessionHasNoErrors();

        $entry = AuditEntry::query()->sole();

        expect($entry->action)->toBe('organization.settings_changed')
            ->and($entry->actor_type)->toBe(ActorType::User)
            ->and($entry->actor_id)->toBe($this->admin->getKey())
            ->and($entry->subject_type)->toBe('organization')
            ->and($entry->subject_id)->toBe($this->acme->getKey())
            ->and($entry->organization_id)->toBe($this->acme->getKey())
            ->and($entry->changes)->toEqual([
                'accent_color' => ['from' => null, 'to' => '#1A2B3C'],
                'leaderboard_enabled' => ['from' => true, 'to' => false],
            ]);

        $this->actingAs($this->admin)->from($this->url)->post($this->url, auditSettingsForm($this->acme->fresh(), [
            'accent_color' => '#000000',
        ]))->assertSessionHasNoErrors();

        expect(AuditEntry::query()->where('action', 'organization.settings_changed')->count())->toBe(2);
    });

    it('writes no entry when a save changes nothing', function () {
        $this->actingAs($this->admin)->from($this->url)->post($this->url, auditSettingsForm($this->acme))
            ->assertSessionHasNoErrors();

        expect(AuditEntry::query()->count())->toBe(0);
    });

    it('writes no entry when the save is rejected', function () {
        $this->actingAs($this->admin)->from($this->url)->post($this->url, auditSettingsForm($this->acme, [
            'accent_color' => 'red',
        ]))->assertSessionHasErrors('accent_color');

        expect(AuditEntry::query()->count())->toBe(0);
    });
});

describe('course_space.archived', function () {
    beforeEach(function () {
        $this->acme = $this->createOrganization(['subdomain' => 'acme']);
        $this->actingAsTenant($this->acme);

        $this->teacher = User::factory()->teacher()->create();
        $this->space = CourseSpace::factory()->for($this->teacher, 'teacher')->create();
        foreach (User::factory()->student()->count(2)->create() as $student) {
            Enrolment::factory()->for($this->space, 'courseSpace')->for($student, 'student')->create();
        }
    });

    it('writes one entry by the teacher with the number of removed enrolments, and none for the restore', function () {
        $this->actingAs($this->teacher)
            ->post($this->tenantUrl($this->acme, "courses/{$this->space->id}/archive"))
            ->assertSessionHasNoErrors();

        $entry = AuditEntry::query()->sole();

        expect($entry->action)->toBe('course_space.archived')
            ->and($entry->actor_type)->toBe(ActorType::User)
            ->and($entry->actor_id)->toBe($this->teacher->getKey())
            ->and($entry->subject_type)->toBe('course_space')
            ->and($entry->subject_id)->toBe($this->space->id)
            ->and($entry->organization_id)->toBe($this->acme->getKey())
            ->and($entry->changes)->toBe(['removed_enrolments' => 2]);

        $this->actingAs($this->teacher)
            ->post($this->tenantUrl($this->acme, "courses/{$this->space->id}/restore"))
            ->assertSessionHasNoErrors();

        expect(AuditEntry::query()->count())->toBe(1);
    });

    it('writes no entry when someone other than the owning teacher tries to archive', function () {
        $other = User::factory()->teacher()->create();

        $this->actingAs($other)
            ->post($this->tenantUrl($this->acme, "courses/{$this->space->id}/archive"))
            ->assertForbidden();

        expect(AuditEntry::query()->count())->toBe(0);
    });
});

describe('foreign key to organizations', function () {
    it('rejects an entry for an organisation that does not exist', function () {
        $insert = fn () => DB::transaction(fn () => DB::table('audit_entries')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => (string) Str::uuid(),
            'actor_type' => 'system',
            'action' => 'x',
            'subject_type' => 'y',
            'subject_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]));

        expect($insert)->toThrow(QueryException::class)
            ->and(AuditEntry::query()->count())->toBe(0);
    });

    it('restricts deleting an organisation that has audit entries', function () {
        $acme = $this->createOrganization(['subdomain' => 'acme']);
        DB::table('audit_entries')->insert([
            'id' => (string) Str::uuid(),
            'organization_id' => $acme->getKey(),
            'actor_type' => 'system',
            'action' => 'x',
            'subject_type' => 'y',
            'subject_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ]);

        $delete = fn () => DB::transaction(fn () => DB::table('organizations')->where('id', $acme->getKey())->delete());

        expect($delete)->toThrow(QueryException::class)
            ->and(Organization::query()->whereKey($acme->getKey())->exists())->toBeTrue();
    });

    it('declares the constraint as on delete restrict', function () {
        $rule = DB::selectOne(
            "select rc.delete_rule from information_schema.referential_constraints rc
             join information_schema.key_column_usage k on k.constraint_name = rc.constraint_name
             where k.table_name = 'audit_entries' and k.column_name = 'organization_id'"
        );

        expect($rule?->delete_rule)->toBe('RESTRICT');
    });
});
