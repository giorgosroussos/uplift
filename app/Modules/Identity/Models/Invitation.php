<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\InvitationFactory;
use App\Modules\Identity\Enums\InvitationStatus;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Exceptions\InvalidInvitationTransition;
use App\Modules\Tenancy\Concerns\BelongsToOrganization;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ENT-Invitation: an email address invited to join an organisation with a
 * role (UC-032), accepted through a single-use link (UC-005).
 *
 * Tenant-owned. The status follows the state model of the design; any other
 * change is rejected and leaves the status unchanged. The token is never
 * stored: token_hash holds a hash of it, encrypted at rest. The email and the
 * token hash never go to logs or error messages.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $email
 * @property Role $role
 * @property string $token_hash
 * @property string $invited_by
 * @property CarbonInterface $expires_at
 * @property InvitationStatus $status
 * @property CarbonInterface|null $email_failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User $inviter
 */
#[UseFactory(InvitationFactory::class)]
class Invitation extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    use HasUuids;
    use MassPrunable;

    /**
     * Days an invitation link stays valid (FLOW-003: single-use link, 7 days).
     */
    public const int VALID_DAYS = 7;

    /**
     * Days an invitation is kept after it stopped being pending (retention).
     */
    public const int RETENTION_DAYS = 30;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'role',
        'invited_by',
        'expires_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation): void {
            if ($invitation->status !== InvitationStatus::initial()) {
                throw InvalidInvitationTransition::initial($invitation->status);
            }
        });

        static::updating(function (Invitation $invitation): void {
            if (! $invitation->isDirty('status')) {
                return;
            }

            /** @var InvitationStatus $from */
            $from = $invitation->getOriginal('status');
            $to = $invitation->status;

            if (! $from->canTransitionTo($to)) {
                $invitation->status = $from;

                throw InvalidInvitationTransition::between($from, $to);
            }
        });
    }

    /**
     * Generates a new single-use token, keeps only its hash and returns the
     * token for the invitation link.
     */
    public function issueToken(): string
    {
        $token = Str::random(48);
        $this->token_hash = self::digest($token);

        return $token;
    }

    public function matchesToken(#[\SensitiveParameter] string $token): bool
    {
        return hash_equals($this->token_hash, self::digest($token));
    }

    /**
     * Moves the invitation to another status and saves it.
     *
     * @throws InvalidInvitationTransition when the state model does not allow it
     */
    public function transitionTo(InvitationStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw InvalidInvitationTransition::between($this->status, $to);
        }

        $this->status = $to;
        $this->save();
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Records the expiry of a pending invitation whose link is past its
     * validity. Returns whether the invitation can still be accepted.
     */
    public function expireIfDue(): bool
    {
        if ($this->isPending() && $this->hasExpired()) {
            $this->transitionTo(InvitationStatus::Expired);
        }

        return $this->isPending();
    }

    /**
     * The invitation email failed after its last retry (DATA-015).
     */
    public function markEmailFailed(): void
    {
        $this->email_failed_at = now();
        $this->save();
    }

    /**
     * Resend (DATA-015): a pending or expired invitation becomes pending with
     * a new token (the old link stops working), a fresh validity of 7 days
     * and no recorded delivery failure. Returns the new token.
     *
     * @throws InvalidInvitationTransition when it is accepted or revoked
     */
    public function reissue(): string
    {
        if ($this->status !== InvitationStatus::Pending && ! $this->status->canTransitionTo(InvitationStatus::Pending)) {
            throw InvalidInvitationTransition::between($this->status, InvitationStatus::Pending);
        }

        $token = $this->issueToken();
        $this->status = InvitationStatus::Pending;
        $this->expires_at = now()->addDays(self::VALID_DAYS);
        $this->email_failed_at = null;
        $this->save();

        return $token;
    }

    public function emailFailed(): bool
    {
        return $this->email_failed_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Retention: deleted 30 days after the invitation stopped being pending
     * (accepted, revoked, expired, or pending past its expiry). Runs across
     * organisations from the scheduler.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        return static::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('status', '!=', InvitationStatus::Pending->value)
                    ->where('updated_at', '<', $cutoff))
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', InvitationStatus::Pending->value)
                    ->where('expires_at', '<', $cutoff)));
    }

    /**
     * A short hash of the token (128 bits of SHA-256), so that its encrypted
     * form fits the string(255) column.
     */
    private static function digest(#[\SensitiveParameter] string $token): string
    {
        return rtrim(strtr(base64_encode(substr(hash('sha256', $token, true), 0, 16)), '+/', '-_'), '=');
    }

    /**
     * Emails are compared case-insensitively: they are stored in lower case.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'status' => InvitationStatus::class,
            'token_hash' => 'encrypted',
            'expires_at' => 'datetime',
            'email_failed_at' => 'datetime',
        ];
    }
}
