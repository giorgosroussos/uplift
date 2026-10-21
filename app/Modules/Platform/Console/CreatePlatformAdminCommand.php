<?php

namespace App\Modules\Platform\Console;

use App\Modules\Platform\Actions\CreatePlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Creates a platform admin (SEC-006). Platform admins are created only by
 * an operator on the console; they set up the required MFA at first login.
 * The password is asked for, never passed as an argument, and no personal
 * data is printed.
 */
class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'platform:create-admin';

    protected $description = 'Create a platform admin (MFA is set up at the first login)';

    public function handle(CreatePlatformAdmin $create): int
    {
        $name = (string) $this->ask('Name');
        $email = (string) $this->ask('Email');
        $password = (string) $this->secret('Password');

        if ($password !== (string) $this->secret('Repeat the password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        try {
            $admin = $create->handle($name, $email, $password);
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $this->info('Platform admin '.$admin->getKey().' created. Two-factor authentication is set up at the first login on /admin/login.');

        return self::SUCCESS;
    }
}
