<?php

namespace App\Modules\Identity\Models;

use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A pending password reset of one email in one organisation. The password
 * broker writes these rows through TenantTokenRepository; the model exists so
 * the table is covered by the organisation scope like every tenant-owned table.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $email
 * @property string $token hashed
 * @property Carbon|null $created_at
 */
class PasswordResetToken extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['email', 'token'];

    /**
     * @var list<string>
     */
    protected $hidden = ['token'];
}
