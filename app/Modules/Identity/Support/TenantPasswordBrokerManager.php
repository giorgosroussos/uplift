<?php

namespace App\Modules\Identity\Support;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use InvalidArgumentException;

/**
 * Password brokers whose database tokens are scoped to the current
 * organisation (TenantTokenRepository) and which do equal work for unknown
 * emails (TenantPasswordBroker).
 */
class TenantPasswordBrokerManager extends PasswordBrokerManager
{
    /**
     * @param  string  $name
     */
    protected function resolve($name): PasswordBroker
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Password resetter [{$name}] is not defined.");
        }

        return new TenantPasswordBroker(
            $this->createTokenRepository($config),
            $this->app['auth']->createUserProvider($config['provider'] ?? null),
            $this->app['hash'],
            $this->app['events'] ?? null,
            timeboxDuration: (int) $this->app['config']->get('auth.timebox_duration', 200000),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        if (($config['driver'] ?? 'database') !== 'database') {
            return parent::createTokenRepository($config);
        }

        $key = (string) $this->app['config']['app.key'];

        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7));
        }

        return new TenantTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
