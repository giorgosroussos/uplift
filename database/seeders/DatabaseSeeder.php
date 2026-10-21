<?php

namespace Database\Seeders;

use App\Modules\Courses\Models\Announcement;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Courses\Models\Enrolment;
use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Development data, every user with the password UserFactory::PASSWORD:
 * - `demo` (demo.<TENANCY_DOMAIN>): admin@, teacher@, student@example.com plus
 *   more teachers and students, course spaces (open, with key, archived),
 *   enrolments, announcements and invitations in every status;
 * - `acme`, Greek by default: admin@, teacher@, student@acme.example.com;
 * - `beta`, suspended: admin@beta.example.com.
 * All users are verified and active, except unverified@ and inactive@example.com in demo.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = app(TenantContext::class);

        $demo = Organization::factory()->create(['name' => 'Demo', 'subdomain' => 'demo']);
        $tenants->run($demo, fn (Organization $organization) => $this->seedDemo($organization));

        $acme = Organization::factory()->create([
            'name' => 'Acme Φροντιστήριο',
            'subdomain' => 'acme',
            'default_locale' => Locale::Greek,
        ]);
        $tenants->run($acme, function (Organization $organization): void {
            $users = User::factory()->for($organization, 'organization');
            $users->orgAdmin()->create(['name' => 'Acme Admin', 'email' => 'admin@acme.example.com', 'nickname' => 'acme-admin']);
            $teacher = $users->teacher()->create(['name' => 'Acme Teacher', 'email' => 'teacher@acme.example.com', 'nickname' => 'acme-teacher']);
            $student = $users->student()->create(['name' => 'Acme Student', 'email' => 'student@acme.example.com', 'nickname' => 'acme-student']);

            $space = CourseSpace::factory()->for($teacher, 'teacher')->create(['title' => 'Μαθηματικά Γ΄ Λυκείου']);
            Enrolment::factory()->for($space, 'courseSpace')->for($student, 'student')->create();
            Announcement::factory()->for($space, 'courseSpace')->create();
        });

        $beta = Organization::factory()->suspended()->create(['name' => 'Beta (suspended)', 'subdomain' => 'beta']);
        $tenants->run($beta, function (Organization $organization): void {
            User::factory()->for($organization, 'organization')->orgAdmin()
                ->create(['name' => 'Beta Admin', 'email' => 'admin@beta.example.com', 'nickname' => 'beta-admin']);
        });

        $this->command->info('Organisations: demo, acme (Greek), beta (suspended). Password for every user: '.UserFactory::PASSWORD);
        $this->command->info('demo: admin@, teacher@, student@example.com; acme: admin@, teacher@, student@acme.example.com; beta: admin@beta.example.com');
    }

    private function seedDemo(Organization $organization): void
    {
        $users = User::factory()->for($organization, 'organization');

        $admin = $users->orgAdmin()->create(['name' => 'Demo Admin', 'email' => 'admin@example.com', 'nickname' => 'demo-admin']);
        $teacher = $users->teacher()->create(['name' => 'Demo Teacher', 'email' => 'teacher@example.com', 'nickname' => 'demo-teacher']);
        $student = $users->student()->create(['name' => 'Demo Student', 'email' => 'student@example.com', 'nickname' => 'demo-student']);

        $otherTeacher = $users->teacher()->create(['name' => 'Second Teacher', 'email' => 'teacher2@example.com', 'nickname' => 'demo-teacher2']);
        $students = $users->student()->count(8)->sequence(fn ($sequence) => [
            'email' => 'student'.($sequence->index + 2).'@example.com',
            'nickname' => 'demo-student'.($sequence->index + 2),
        ])->create()->prepend($student);

        $users->student()->unverified()->create(['name' => 'Unverified Student', 'email' => 'unverified@example.com', 'nickname' => 'demo-unverified']);
        $users->student()->inactive()->create(['name' => 'Inactive Student', 'email' => 'inactive@example.com', 'nickname' => 'demo-inactive']);

        $algebra = CourseSpace::factory()->for($teacher, 'teacher')->create(['title' => 'Algebra I']);
        $physics = CourseSpace::factory()->for($teacher, 'teacher')->withKey()->create(['title' => 'Physics (key K7P2Q)']);
        $history = CourseSpace::factory()->for($otherTeacher, 'teacher')->create(['title' => 'Modern History']);
        CourseSpace::factory()->for($teacher, 'teacher')->archived()->create(['title' => 'Algebra I (last year)']);

        foreach ($students as $index => $enrolled) {
            Enrolment::factory()->for($algebra, 'courseSpace')->for($enrolled, 'student')->create();

            if ($index % 2 === 0) {
                Enrolment::factory()->for($physics, 'courseSpace')->for($enrolled, 'student')->create();
            }
            if ($index < 3) {
                Enrolment::factory()->for($history, 'courseSpace')->for($enrolled, 'student')->create();
            }
        }

        Announcement::factory()->count(2)->for($algebra, 'courseSpace')->create();
        Announcement::factory()->for($history, 'courseSpace')->create();

        $invitations = Invitation::factory()->for($organization, 'organization')->state(['invited_by' => $admin->getKey()]);
        $invitations->create(['email' => 'invited.student@example.com']);
        $invitations->role(Role::Teacher)->create(['email' => 'invited.teacher@example.com']);
        $invitations->sentDaysAgo(10)->inStatus(InvitationStatus::Expired)->create(['email' => 'expired.invite@example.com']);
        $invitations->inStatus(InvitationStatus::Revoked)->create(['email' => 'revoked.invite@example.com']);
    }
}
