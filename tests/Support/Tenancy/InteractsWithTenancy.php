<?php

namespace Tests\Support\Tenancy;

use App\Modules\Tenancy\Exceptions\TenantMismatch;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationScope;
use App\Modules\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;

/**
 * Reusable tenancy helpers for feature tests (used by Tests\TestCase).
 *
 * Every tenant-owned model gets a dedicated isolation test, e.g.:
 *
 *     it('isolates course spaces per organisation', function () {
 *         $this->assertTenantIsolated(CourseSpace::class, fn () => CourseSpace::factory()->create());
 *     });
 */
trait InteractsWithTenancy
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createOrganization(array $attributes = []): Organization
    {
        return Organization::factory()->create($attributes);
    }

    /**
     * Makes the organisation the current tenant for code run by the test itself.
     * HTTP requests resolve their tenant from the host instead: use tenantUrl().
     */
    protected function actingAsTenant(Organization $organization): static
    {
        app(TenantContext::class)->set($organization);

        return $this;
    }

    protected function withoutTenant(): static
    {
        app(TenantContext::class)->clear();

        return $this;
    }

    /**
     * @template T
     *
     * @param  callable(Organization): T  $callback
     * @return T
     */
    protected function asTenant(Organization $organization, callable $callback): mixed
    {
        return app(TenantContext::class)->run($organization, $callback);
    }

    /**
     * Absolute URL of a path on the organisation's subdomain.
     */
    protected function tenantUrl(Organization $organization, string $path = '/'): string
    {
        return $this->hostUrl($organization->subdomain.'.'.config('tenancy.domain'), $path);
    }

    /**
     * Absolute URL of a path on the apex domain.
     */
    protected function apexUrl(string $path = '/'): string
    {
        return $this->hostUrl((string) config('tenancy.domain'), $path);
    }

    /**
     * Asserts the standard isolation rules for a tenant-owned model:
     * rows of another tenant are invisible, nothing is visible without a
     * tenant, rows cannot be created for another tenant and cannot be moved
     * to another tenant.
     *
     * @param  class-string<Model>  $model
     * @param  Closure(Organization): Model  $create  creates one row while the given organisation is the tenant
     */
    protected function assertTenantIsolated(string $model, Closure $create): void
    {
        new $model; // boots the model, which registers its global scopes

        Assert::assertTrue(
            $model::hasGlobalScope(OrganizationScope::class),
            "{$model} has no organization_id global scope.",
        );

        $a = $this->createOrganization();
        $b = $this->createOrganization();

        $ownRow = $this->asTenant($a, $create);
        $otherRow = $this->asTenant($b, $create);

        Assert::assertSame($a->getKey(), $ownRow->getAttribute('organization_id'));
        Assert::assertSame($b->getKey(), $otherRow->getAttribute('organization_id'));

        $this->asTenant($a, function () use ($model, $ownRow, $otherRow, $b): void {
            Assert::assertSame([$ownRow->getKey()], $model::query()->pluck((new $model)->getKeyName())->all());
            Assert::assertNull($model::query()->find($otherRow->getKey()));

            $foreign = $otherRow->replicate();
            $foreign->setAttribute('organization_id', $b->getKey());
            $this->assertThrowsTenantMismatch(fn () => $foreign->save(), "{$model} could be created for another tenant.");

            $ownRow->setAttribute('organization_id', $b->getKey());
            $this->assertThrowsTenantMismatch(fn () => $ownRow->save(), "{$model} could be moved to another tenant.");
            $ownRow->setAttribute('organization_id', $ownRow->getOriginal('organization_id'));
        });

        $context = app(TenantContext::class);
        $previous = $context->current();
        $context->clear();

        try {
            Assert::assertSame(0, $model::query()->count(), "{$model} rows are visible without a tenant.");
        } finally {
            if ($previous !== null) {
                $context->set($previous);
            }
        }
    }

    private function assertThrowsTenantMismatch(Closure $write, string $message): void
    {
        try {
            $write();
        } catch (TenantMismatch) {
            return;
        }

        Assert::fail($message);
    }

    private function hostUrl(string $host, string $path): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';

        return $scheme.'://'.$host.'/'.ltrim($path, '/');
    }
}
