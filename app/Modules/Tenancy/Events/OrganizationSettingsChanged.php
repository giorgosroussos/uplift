<?php

namespace App\Modules\Tenancy\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An org admin saved changed organisation settings (UC-033, DATA-011);
 * audited by P2-T7 as organization.settings_changed (SEC-010).
 *
 * Dispatched after the change has committed, once per save that changed at
 * least one field. $changes holds {field: {from, to}} of scalar values only;
 * for the logo that is its storage path, never the file contents.
 */
final class OrganizationSettingsChanged
{
    use Dispatchable;

    /**
     * @param  array<string, array{from: string|bool|null, to: string|bool|null}>  $changes
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $actorId,
        public readonly array $changes,
    ) {}
}
