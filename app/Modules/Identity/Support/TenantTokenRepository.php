<?php

namespace App\Modules\Identity\Support;

use App\Modules\Tenancy\Exceptions\TenantMismatch;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Password reset tokens of the current organisation only: the same email in
 * another organisation has its own token, and a token issued on one
 * subdomain is never valid on another. Fails closed without a tenant.
 */
class TenantTokenRepository extends DatabaseTokenRepository
{
    /**
     * Expired tokens of every organisation (auth:clear-resets).
     */
    public function deleteExpired()
    {
        $this->connection->table($this->table)
            ->where('created_at', '<', Carbon::now()->subSeconds($this->expires))
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getPayload($email, #[\SensitiveParameter] $token)
    {
        $organizationId = $this->organizationId();

        if ($organizationId === null) {
            throw TenantMismatch::missingOrganization(self::class);
        }

        return [
            'id' => (string) Str::uuid7(),
            'organization_id' => $organizationId,
            ...parent::getPayload($email, $token),
        ];
    }

    protected function getTable(): Builder
    {
        $organizationId = $this->organizationId();
        $query = $this->connection->table($this->table);

        return $organizationId === null
            ? $query->whereRaw('1 = 0')
            : $query->where('organization_id', $organizationId);
    }

    private function organizationId(): ?string
    {
        $organization = app(TenantContext::class)->current();

        return $organization instanceof Organization ? (string) $organization->getKey() : null;
    }
}
