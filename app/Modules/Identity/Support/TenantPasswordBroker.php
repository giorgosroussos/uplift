<?php

namespace App\Modules\Identity\Support;

use Closure;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Sending a reset link and resetting a password cost the same whether or not
 * the email has an account (UC-004): an unknown email and a throttled request
 * pay a dummy token hash, and an unknown email pays a dummy token check,
 * inside the framework's Timebox, because argon2id takes longer than the
 * Timebox itself.
 */
class TenantPasswordBroker extends PasswordBroker
{
    public function __construct(
        #[\SensitiveParameter] TokenRepositoryInterface $tokens,
        UserProvider $users,
        private readonly Hasher $hasher,
        ?Dispatcher $dispatcher = null,
        int $timeboxDuration = 200000,
    ) {
        parent::__construct($tokens, $users, $dispatcher, timeboxDuration: $timeboxDuration);
    }

    /**
     * An unknown email pays the same token check as a registered one.
     *
     * @param  array<string, mixed>  $credentials
     */
    protected function validateReset(#[\SensitiveParameter] array $credentials)
    {
        if (is_null($user = $this->getUser($credentials))) {
            EqualWork::check($this->hasher, (string) ($credentials['token'] ?? ''));

            return static::INVALID_USER;
        }

        if (! $this->tokens->exists($user, (string) ($credentials['token'] ?? ''))) {
            return static::INVALID_TOKEN;
        }

        return $user;
    }

    /**
     * Same flow as the framework's, plus equal hashing work on every path.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function sendResetLink(#[\SensitiveParameter] array $credentials, ?Closure $callback = null)
    {
        return $this->timebox->call(function () use ($credentials, $callback) {
            $user = $this->getUser($credentials);

            if (is_null($user)) {
                EqualWork::make($this->hasher);

                return static::INVALID_USER;
            }

            if ($this->tokens->recentlyCreatedToken($user)) {
                EqualWork::make($this->hasher);

                return static::RESET_THROTTLED;
            }

            $token = $this->tokens->create($user);

            if ($callback) {
                return $callback($user, $token) ?? static::RESET_LINK_SENT;
            }

            $user->sendPasswordResetNotification($token);

            $this->events?->dispatch(new PasswordResetLinkSent($user));

            return static::RESET_LINK_SENT;
        }, $this->timeboxDuration);
    }
}
