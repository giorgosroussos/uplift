<?php

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Events\OrganizationSettingsChanged;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\OrganizationLogo;
use BackedEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * UC-033: an org admin saves the organisation settings (DATA-011). The
 * subdomain is never changed here. After the save has committed, one
 * OrganizationSettingsChanged carries every changed field as {from, to}; a
 * save that changes nothing dispatches no event.
 */
final class UpdateOrganizationSettings
{
    /**
     * The settings an organisation may change; everything else is ignored.
     *
     * @var list<string>
     */
    public const array FIELDS = [
        'accent_color',
        'student_term_el',
        'student_term_en',
        'teacher_term_el',
        'teacher_term_en',
        'grading_scale',
        'leaderboard_enabled',
        'default_locale',
    ];

    public function __construct(private readonly OrganizationLogo $logo) {}

    /**
     * @param  array<string, mixed>  $settings  validated values of FIELDS
     * @param  UploadedFile|null  $logo  a new logo (already validated)
     * @param  bool  $removeLogo  remove the current logo (ignored when a new one is given)
     */
    public function handle(Organization $organization, string $actorId, array $settings, ?UploadedFile $logo = null, bool $removeLogo = false): void
    {
        $newLogoPath = $logo === null ? null : $this->logo->store($organization, $logo);

        try {
            [$changes, $oldLogoPath] = DB::transaction(function () use ($organization, $settings, $logo, $removeLogo, $newLogoPath): array {
                /** @var Organization $locked */
                $locked = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());

                $locked->fill(Arr::only($settings, self::FIELDS));

                if ($logo !== null) {
                    $locked->logo_path = $newLogoPath;
                } elseif ($removeLogo) {
                    $locked->logo_path = null;
                }

                $changes = [];

                foreach (array_keys($locked->getDirty()) as $field) {
                    $changes[$field] = [
                        'from' => $this->scalar($locked->getOriginal($field)),
                        'to' => $this->scalar($locked->getAttribute($field)),
                    ];
                }

                $oldLogoPath = $locked->isDirty('logo_path') ? $locked->getOriginal('logo_path') : null;

                $locked->save();

                return [$changes, $oldLogoPath];
            });
        } catch (Throwable $exception) {
            $this->logo->delete($newLogoPath);

            throw $exception;
        }

        $organization->refresh();

        // The replaced or removed file is no longer referenced.
        $this->logo->delete(is_string($oldLogoPath) ? $oldLogoPath : null);

        if ($changes !== []) {
            OrganizationSettingsChanged::dispatch((string) $organization->getKey(), $actorId, $changes);
        }
    }

    private function scalar(mixed $value): string|bool|null
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            is_bool($value), is_string($value), $value === null => $value,
            default => (string) $value,
        };
    }
}
