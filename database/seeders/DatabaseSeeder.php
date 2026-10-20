<?php

namespace Database\Seeders;

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Development data: organisation `demo` (demo.<TENANCY_DOMAIN>) with one user
 * per role, all with the password UserFactory::PASSWORD.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Demo',
            'subdomain' => 'demo',
        ]);

        $users = User::factory()->for($organization, 'organization');

        $users->orgAdmin()->create(['name' => 'Demo Admin', 'email' => 'admin@example.com', 'nickname' => 'demo-admin']);
        $users->teacher()->create(['name' => 'Demo Teacher', 'email' => 'teacher@example.com', 'nickname' => 'demo-teacher']);
        $users->student()->create(['name' => 'Demo Student', 'email' => 'student@example.com', 'nickname' => 'demo-student']);

        $this->command->info('Users of demo: admin@, teacher@, student@example.com / '.UserFactory::PASSWORD);
    }
}
