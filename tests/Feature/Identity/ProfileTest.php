<?php

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\Locale;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-006 — Manage my profile: name, nickname, preset avatar, language and
| password (DATA-014); OWN-001 on the user's own record.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme', 'default_locale' => Locale::Greek]);
    $this->beta = $this->createOrganization(['subdomain' => 'beta']);

    $acmeUsers = User::factory()->for($this->acme, 'organization');
    $this->student = $acmeUsers->student()->create([
        'name' => 'Sam Student',
        'email' => 'sam@acme.test',
        'date_of_birth' => '2012-05-01',
        'is_minor' => true,
    ]);
    $this->teacher = $acmeUsers->teacher()->create(['name' => 'Tom Teacher']);
    $this->admin = $acmeUsers->orgAdmin()->create(['name' => 'Alice Admin']);

    $this->betaUser = User::factory()->for($this->beta, 'organization')->student()->create();
});

/**
 * @return array<string, mixed>
 */
function profileRow(User $user): array
{
    return User::query()->withoutGlobalScopes()->findOrFail($user->getKey())->getAttributes();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function profileInput(User $user, array $overrides = []): array
{
    return [
        'name' => $user->name,
        'nickname' => $user->nickname,
        'avatar' => $user->avatar ?? '',
        'locale' => $user->locale->value ?? '',
        ...$overrides,
    ];
}

describe('profile page', function () {
    it('shows the user\'s own profile with email, role, date of birth and minor flag read-only', function () {
        $this->actingAs($this->student)
            ->get($this->tenantUrl($this->acme, 'settings/profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Identity/Settings/Profile')
                ->where('profile.name', 'Sam Student')
                ->where('profile.email', 'sam@acme.test')
                ->where('profile.role', 'student')
                ->where('profile.date_of_birth', '2012-05-01')
                ->where('profile.is_minor', true)
                ->where('profile.locale', null)
                ->where('avatars', array_map(fn (int $i) => sprintf('avatar-%02d', $i), range(1, 12)))
                ->where('locales', ['el', 'en'])
                ->where('organizationLocale', 'el'));
    });

    it('redirects /settings to the profile', function () {
        $this->actingAs($this->student)
            ->get($this->tenantUrl($this->acme, 'settings'))
            ->assertRedirect();
    });

    it('requires login', function () {
        $this->get($this->tenantUrl($this->acme, 'settings/profile'))->assertRedirect();
        $this->patch($this->tenantUrl($this->acme, 'settings/profile'), ['nickname' => 'Fox_7'])->assertRedirect();
        $this->put($this->tenantUrl($this->acme, 'settings/password'), [])->assertRedirect();
    });

    it('bundles the 12 preset avatars', function () {
        foreach (range(1, 12) as $i) {
            expect(public_path(sprintf('avatars/avatar-%02d.svg', $i)))->toBeFile();
        }
    });
});

describe('editing the profile', function () {
    it('saves nickname, avatar and language, and leaderboards, broadcasts and the UI use them', function () {
        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, [
                'nickname' => 'Fox_7',
                'avatar' => 'avatar-03',
                'locale' => 'en',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->tenantUrl($this->acme, 'settings/profile'));

        $student = $this->student->fresh();
        expect($student->nickname)->toBe('Fox_7')
            ->and($student->avatar)->toBe('avatar-03')
            ->and($student->locale)->toBe(Locale::English)
            // Leaderboards and live broadcasts show only nickname and avatar (SEC-008, SEC-010).
            ->and($student->publicProfile())->toBe([
                'id' => $student->getKey(),
                'nickname' => 'Fox_7',
                'avatar' => 'avatar-03',
            ]);

        // The organisation default is Greek; the user's choice wins.
        $this->actingAs($student)
            ->get($this->tenantUrl($this->acme, 'settings/profile'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentLocale', 'en')
                ->where('auth.user.nickname', 'Fox_7')
                ->where('auth.user.avatar', 'avatar-03'));

        expect(app()->getLocale())->toBe('en');
    });

    it('goes back to the organisation default language when the language is emptied', function () {
        $this->student->forceFill(['locale' => Locale::English])->save();

        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, ['locale' => '']))
            ->assertSessionHasNoErrors();

        expect($this->student->fresh()->locale)->toBeNull();

        $this->get($this->tenantUrl($this->acme, 'settings/profile'))
            ->assertInertia(fn (Assert $page) => $page->where('currentLocale', 'el'));
    });

    it('removes the avatar so the initials are shown', function () {
        $this->student->forceFill(['avatar' => 'avatar-05'])->save();

        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, ['avatar' => '']))
            ->assertSessionHasNoErrors();

        expect($this->student->fresh()->avatar)->toBeNull();
    });

    it('lets the user keep their own nickname in another case', function () {
        $this->student->forceFill(['nickname' => 'Fox_7'])->save();

        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, ['nickname' => 'FOX_7']))
            ->assertSessionHasNoErrors();

        expect($this->student->fresh()->nickname)->toBe('FOX_7');
    });

    it('rejects a nickname another user of the organisation has, ignoring case; a user of beta may use it', function () {
        $this->teacher->forceFill(['nickname' => 'Fox_7'])->save();
        $before = profileRow($this->student);

        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, ['nickname' => 'fox_7']))
            ->assertSessionHasErrors(['nickname' => __('profile.validation.nickname_taken')]);

        expect(profileRow($this->student))->toBe($before);

        $this->actingAs($this->betaUser)
            ->patch($this->tenantUrl($this->beta, 'settings/profile'), profileInput($this->betaUser, ['nickname' => 'fox_7']))
            ->assertSessionHasNoErrors();

        expect($this->betaUser->fresh()->nickname)->toBe('fox_7');
    });

    it('rejects an invalid nickname and saves nothing', function (string $nickname, string $reason) {
        $before = profileRow($this->student);

        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, [
                'nickname' => $nickname,
                'avatar' => 'avatar-01',
            ]))
            ->assertSessionHasErrors(['nickname' => __("profile.validation.{$reason}", ['min' => 3, 'max' => 30])]);

        expect(profileRow($this->student))->toBe($before);
    })->with([
        'too short' => ['ab', 'nickname_length'],
        '31 characters' => [str_repeat('a', 31), 'nickname_length'],
        'space' => ['fox 7', 'nickname_format'],
        'at sign' => ['fox@7', 'nickname_format'],
        'emoji' => ['fox🦊7', 'nickname_format'],
    ]);

    it('rejects an empty nickname', function () {
        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, ['nickname' => '']))
            ->assertSessionHasErrors('nickname');
    });

    it('accepts nicknames of letters of any script, digits, _ - and .', function (string $nickname) {
        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, ['nickname' => $nickname]))
            ->assertSessionHasNoErrors();

        expect($this->student->fresh()->nickname)->toBe($nickname);
    })->with([
        'Greek' => ['Αλεπού.7'],
        '3 characters' => ['abc'],
        '30 characters' => [str_repeat('β', 30)],
        'hyphen and underscore' => ['a-b_c.d'],
    ]);

    it('trims the nickname', function () {
        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, ['nickname' => '  Fox_7  ']))
            ->assertSessionHasNoErrors();

        expect($this->student->fresh()->nickname)->toBe('Fox_7');
    });

    it('enforces case-insensitive nickname uniqueness in the database too', function () {
        $this->teacher->forceFill(['nickname' => 'Fox_7'])->save();

        expect(fn () => DB::table('users')->where('id', $this->student->getKey())->update(['nickname' => 'FOX_7']))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('accepts only the preset avatar keys, never an upload', function (mixed $avatar) {
        $before = profileRow($this->student);

        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, ['avatar' => $avatar]))
            ->assertSessionHasErrors('avatar');

        expect(profileRow($this->student))->toBe($before);
    })->with([
        'avatar-00' => ['avatar-00'],
        'avatar-13' => ['avatar-13'],
        'url' => ['https://example.com/me.png'],
        'path' => ['../avatars/avatar-01.svg'],
        'upload' => [fn () => UploadedFile::fake()->image('me.png')],
    ]);

    it('rejects a language other than Greek and English', function () {
        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, ['locale' => 'fr']))
            ->assertSessionHasErrors('locale');

        expect($this->student->fresh()->locale)->toBeNull();
    });

    it('never changes email, role, date of birth, minor flag or organisation from the profile form', function () {
        $before = profileRow($this->student);

        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, [
                'email' => 'new@acme.test',
                'role' => 'org_admin',
                'date_of_birth' => '1990-01-01',
                'is_minor' => false,
                'organization_id' => $this->beta->getKey(),
                'is_active' => false,
                'email_verified_at' => null,
                'password' => 'another-password-123',
                'two_factor_secret' => 'x',
            ]))
            ->assertSessionHasNoErrors();

        $after = profileRow($this->student);
        foreach (['email', 'role', 'date_of_birth', 'is_minor', 'organization_id', 'is_active', 'email_verified_at', 'password', 'two_factor_secret'] as $field) {
            expect($after[$field])->toBe($before[$field], "{$field} changed");
        }
    });
});

describe('changing the password', function () {
    it('changes the password with the correct current one and logs out the other session', function () {
        $oldHash = $this->student->password;
        $oldSessionHash = Auth::guard('web')->hashPasswordForCookie($oldHash);

        // This session.
        $this->actingAs($this->student)
            ->withSession(['password_hash_web' => $oldSessionHash])
            ->put($this->tenantUrl($this->acme, 'settings/password'), [
                'current_password' => UserFactory::PASSWORD,
                'password' => 'a-brand-new-secret',
                'password_confirmation' => 'a-brand-new-secret',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->tenantUrl($this->acme, 'settings/password'));

        $student = $this->student->fresh();
        expect(Hash::check('a-brand-new-secret', $student->password))->toBeTrue()
            ->and(Hash::info($student->password)['algoName'])->toBe('argon2id');

        // This session keeps working: it was given the new hash.
        $this->get($this->tenantUrl($this->acme, 'dashboard'))->assertOk();
        $this->assertAuthenticatedAs($student);

        // The other session still carries the old hash and is logged out.
        Auth::guard('web')->logout();
        $this->flushSession();
        $this->actingAs($student)
            ->withSession(['password_hash_web' => $oldSessionHash])
            ->get($this->tenantUrl($this->acme, 'dashboard'))
            ->assertRedirect();
        $this->assertGuest();
    });

    it('changes nothing with a wrong current password', function () {
        $before = profileRow($this->student);
        $sessionHash = Auth::guard('web')->hashPasswordForCookie($this->student->password);

        $this->actingAs($this->student)
            ->withSession(['password_hash_web' => $sessionHash])
            ->put($this->tenantUrl($this->acme, 'settings/password'), [
                'current_password' => 'not-my-password',
                'password' => 'a-brand-new-secret',
                'password_confirmation' => 'a-brand-new-secret',
            ])
            ->assertSessionHasErrors('current_password');

        expect(profileRow($this->student))->toBe($before);

        // The other session is not affected.
        $this->flushSession();
        $this->actingAs($this->student->fresh())
            ->withSession(['password_hash_web' => $sessionHash])
            ->get($this->tenantUrl($this->acme, 'dashboard'))
            ->assertOk();
    });

    it('applies the password rules of SEC-010', function (string $password, string $confirmation) {
        $before = profileRow($this->student);

        $this->actingAs($this->student)
            ->put($this->tenantUrl($this->acme, 'settings/password'), [
                'current_password' => UserFactory::PASSWORD,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ])
            ->assertSessionHasErrors('password');

        expect(profileRow($this->student))->toBe($before);
    })->with([
        'too short' => ['short1', 'short1'],
        'not confirmed' => ['a-brand-new-secret', 'something-else-123'],
    ]);

    it('shows the password page', function () {
        $this->actingAs($this->teacher)
            ->get($this->tenantUrl($this->acme, 'settings/password'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Identity/Settings/Password'));
    });
});

describe('OWN-001 on the profile', function () {
    it('lets every role read and edit only their own record', function (string $actor) {
        $actor = $this->{$actor};
        $others = array_values(array_filter(
            [$this->student, $this->teacher, $this->admin, $this->betaUser],
            fn (User $user) => $user->getKey() !== $actor->getKey(),
        ));
        $before = array_map(fn (User $user) => profileRow($user), $others);

        foreach ($others as $other) {
            // The settings routes take no user id: any id given is ignored.
            $this->actingAs($actor)
                ->get($this->tenantUrl($this->acme, 'settings/profile').'?user='.$other->getKey())
                ->assertInertia(fn (Assert $page) => $page->where('profile.email', $actor->email));

            $this->patch($this->tenantUrl($this->acme, 'settings/profile'), [
                ...profileInput($actor, ['nickname' => 'Taken_'.Str::random(4)]),
                'id' => $other->getKey(),
                'user' => $other->getKey(),
            ])->assertSessionHasNoErrors();

            $this->put($this->tenantUrl($this->acme, 'settings/password'), [
                'id' => $other->getKey(),
                'current_password' => UserFactory::PASSWORD,
                'password' => 'another-secret-pass',
                'password_confirmation' => 'another-secret-pass',
            ]);
            $actor->refresh();
        }

        expect(array_map(fn (User $user) => profileRow($user), $others))->toBe($before);
    })->with(['student', 'teacher', 'admin']);

    it('denies a student or teacher any other user record through the policy', function (string $actor) {
        $actor = $this->{$actor};

        foreach ([$this->student, $this->teacher, $this->admin, $this->betaUser] as $other) {
            if ($other->is($actor)) {
                expect($actor->can('view', $other))->toBeTrue()
                    ->and($actor->can('update', $other))->toBeTrue();

                continue;
            }

            expect($actor->can('view', $other))->toBeFalse()
                ->and($actor->can('update', $other))->toBeFalse();
        }
    })->with(['student', 'teacher']);

    it('denies an org admin users of another organisation through the policy', function () {
        expect($this->admin->can('view', $this->betaUser))->toBeFalse()
            ->and($this->admin->can('update', $this->betaUser))->toBeFalse()
            ->and($this->admin->can('manage', $this->betaUser))->toBeFalse()
            ->and($this->admin->can('update', $this->student))->toBeTrue();
    });

    it('does not let a user change their own role through any settings route', function () {
        $this->actingAs($this->student)
            ->patch($this->tenantUrl($this->acme, 'settings/profile'), profileInput($this->student, ['role' => Role::OrgAdmin->value]));

        expect($this->student->fresh()->role)->toBe(Role::Student);
    });
});
