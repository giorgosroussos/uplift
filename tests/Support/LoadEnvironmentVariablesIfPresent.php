<?php

namespace Tests\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;

/**
 * Tests take their environment from phpunit.xml (and CI), so a checkout
 * without a .env file must not make phpdotenv emit warnings.
 */
final class LoadEnvironmentVariablesIfPresent extends LoadEnvironmentVariables
{
    public function bootstrap(Application $app): void
    {
        if (! is_file($app->environmentFilePath())) {
            return;
        }

        parent::bootstrap($app);
    }
}
