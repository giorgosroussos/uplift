<?php

namespace App\Modules\Activities\Support;

use App\Modules\Activities\Contracts\ActivityType;
use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Exceptions\UnknownActivityType;
use App\Modules\Activities\Models\Activity;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Registry of the ActivityType implementations that modules register
 * (ARCH-004). Bound as a singleton by ActivitiesServiceProvider.
 */
final class ActivityTypes
{
    /**
     * @var array<string, ActivityType>
     */
    private array $types = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Registers (or replaces) the implementation of its kind.
     *
     * @param  class-string<ActivityType>|ActivityType  $type
     */
    public function register(string|ActivityType $type): void
    {
        $instance = is_string($type) ? $this->container->make($type) : $type;

        if (! $instance instanceof ActivityType) {
            throw new InvalidArgumentException('Activity types must implement '.ActivityType::class.'.');
        }

        $this->types[$instance->kind()->value] = $instance;
    }

    public function has(ActivityKind $kind): bool
    {
        return isset($this->types[$kind->value]);
    }

    /**
     * @throws UnknownActivityType
     */
    public function get(ActivityKind $kind): ActivityType
    {
        return $this->types[$kind->value] ?? throw UnknownActivityType::for($kind);
    }

    /**
     * @throws UnknownActivityType
     */
    public function for(Activity $activity): ActivityType
    {
        return $this->get($activity->type);
    }

    /**
     * The registered kinds, in the order of ActivityKind.
     *
     * @return list<ActivityKind>
     */
    public function kinds(): array
    {
        return array_values(array_filter(ActivityKind::cases(), fn (ActivityKind $kind): bool => $this->has($kind)));
    }
}
