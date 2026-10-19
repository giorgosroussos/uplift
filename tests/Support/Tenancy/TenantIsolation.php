<?php

namespace Tests\Support\Tenancy;

use App\Modules\Tenancy\Support\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Finds tenant-owned tables (with an organization_id column) and the Eloquent
 * models that map them, so a test can fail when a tenant-owned model lacks
 * the organization_id global scope.
 */
final class TenantIsolation
{
    /**
     * Tables of the current schema that have an organization_id column.
     *
     * @return list<string>
     */
    public static function tenantOwnedTables(): array
    {
        /** @var list<string> $tables */
        $tables = DB::table('information_schema.columns')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('column_name', 'organization_id')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        return $tables;
    }

    /**
     * Concrete Eloquent models of the application (app/Models and
     * app/Modules/<Name>/Models).
     *
     * @return list<class-string<Model>>
     */
    public static function applicationModels(): array
    {
        $directories = array_values(array_filter(
            [app_path('Models'), ...(glob(app_path('Modules/*/Models'), GLOB_ONLYDIR) ?: [])],
            'is_dir',
        ));

        $models = [];

        foreach (Finder::create()->files()->name('*.php')->in($directories) as $file) {
            $class = 'App\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                Str::after($file->getRealPath(), realpath(app_path()).DIRECTORY_SEPARATOR),
            );

            if (class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $models[] = $class;
            }
        }

        sort($models);

        return $models;
    }

    /**
     * Models whose table is tenant-owned but which lack the organization_id
     * global scope.
     *
     * @param  iterable<class-string<Model>>  $models
     * @param  list<string>  $tenantOwnedTables
     * @return list<class-string<Model>>
     */
    public static function modelsMissingScope(iterable $models, array $tenantOwnedTables): array
    {
        $missing = [];

        foreach ($models as $model) {
            if (in_array((new $model)->getTable(), $tenantOwnedTables, true) && ! $model::hasGlobalScope(OrganizationScope::class)) {
                $missing[] = $model;
            }
        }

        return $missing;
    }

    /**
     * Tenant-owned tables that no model maps (so no scope protects them).
     *
     * @param  iterable<class-string<Model>>  $models
     * @param  list<string>  $tenantOwnedTables
     * @return list<string>
     */
    public static function tablesWithoutModel(iterable $models, array $tenantOwnedTables): array
    {
        $mapped = [];

        foreach ($models as $model) {
            $mapped[] = (new $model)->getTable();
        }

        return array_values(array_diff($tenantOwnedTables, $mapped));
    }
}
