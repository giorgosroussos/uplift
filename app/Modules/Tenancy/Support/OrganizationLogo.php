<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Models\Organization;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The organisation logo (DATA-011): stored on a private Storage disk
 * (outside the public directory, SEC-010) and served through the tenant
 * route organization.logo.
 */
final class OrganizationLogo
{
    public function __construct(private readonly SvgSanitizer $svgSanitizer) {}

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('tenancy.logo_disk'));
    }

    /**
     * URL of the logo on the organisation's own subdomain, or null without a
     * logo. The version changes with every new file, so caches never show an
     * old logo.
     */
    public static function url(Organization $organization): ?string
    {
        if ($organization->logo_path === null) {
            return null;
        }

        return route('organization.logo', ['v' => substr(sha1($organization->logo_path), 0, 12)], false);
    }

    /**
     * Stores an uploaded png, jpg or svg (sanitised) and returns its path.
     */
    public function store(Organization $organization, UploadedFile $file): string
    {
        $extension = match ($file->guessExtension()) {
            'png' => 'png',
            'jpg', 'jpeg' => 'jpg',
            'svg' => 'svg',
            default => throw new RuntimeException('Unsupported logo type.'),
        };

        $path = 'organizations/'.$organization->getKey().'/logo-'.Str::random(20).'.'.$extension;

        $contents = $extension === 'svg'
            ? $this->svgSanitizer->sanitize((string) $file->get())
            : $file->get();

        if ($contents === null || $contents === false || ! $this->disk()->put($path, $contents)) {
            throw new RuntimeException('The logo could not be stored.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            $this->disk()->delete($path);
        }
    }
}
