<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Events\OrganizationRegistered;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\SubdomainAvailability;
use App\Modules\Tenancy\Enums\GradingScale;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * UC-001: creates an active organisation with the initial settings of
 * DATA-010 and its first org admin, then (after commit) queues the admin's
 * verification mail and dispatches OrganizationRegistered.
 */
final class RegisterOrganization
{
    /**
     * Initial organisation settings (DATA-010); changeable later (UC-033).
     *
     * @var array<string, mixed>
     */
    public const array INITIAL_SETTINGS = [
        'student_term_el' => 'Μαθητής',
        'student_term_en' => 'Student',
        'teacher_term_el' => 'Καθηγητής',
        'teacher_term_en' => 'Teacher',
        'grading_scale' => GradingScale::Scale0To10,
        'leaderboard_enabled' => true,
    ];

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  array{organization_name: string, subdomain: string, name: string, email: string, password: string}  $input  validated input
     * @param  Locale  $locale  UI locale of the sign-up request: the organisation's default locale
     *
     * @throws ValidationException when the subdomain was taken concurrently
     */
    public function handle(array $input, Locale $locale): User
    {
        try {
            /** @var User $admin */
            $admin = DB::transaction(function () use ($input, $locale): User {
                $organization = Organization::query()->create([
                    ...self::INITIAL_SETTINGS,
                    'name' => $input['organization_name'],
                    'subdomain' => $input['subdomain'],
                    'default_locale' => $locale,
                ]);

                return $this->tenant->run($organization, fn (Organization $organization): User => User::query()->create([
                    'role' => Role::OrgAdmin,
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => $input['password'],
                ]));
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'subdomain' => SubdomainAvailability::message(SubdomainAvailability::TAKEN),
            ]);
        }

        $admin->sendEmailVerificationNotification();

        OrganizationRegistered::dispatch($admin->organization_id, (string) $admin->getKey());

        return $admin;
    }
}
