<?php

namespace App\Modules\Identity\Support;

use Illuminate\Support\Facades\DB;

/**
 * Serialises the transactions that check and claim an email address across
 * the platform (SEC-004: one organisation per user), with a PostgreSQL
 * transaction-level advisory lock on a hash of the address.
 */
final class EmailLock
{
    /**
     * Must run inside a transaction; the lock is released when it ends.
     */
    public static function acquire(#[\SensitiveParameter] string $email): void
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['identity.email:'.mb_strtolower(trim($email))]);
    }
}
