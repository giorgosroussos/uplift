<?php

namespace App\Modules\Assessment\Support;

use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DATA-017 and SEC-010: uploaded question CSV files on the private import
 * disk, under question-imports/<organization_id>/<user_id>/<id>.csv. A file
 * is found only under its uploader's own directory, so only the uploader
 * can see, confirm or cancel an import.
 */
final class QuestionImportFiles
{
    public const string DIRECTORY = 'question-imports';

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('assessment.question_import_disk'));
    }

    /**
     * Stores the contents for the uploader and returns the import id.
     */
    public function put(User $uploader, string $contents): string
    {
        $id = (string) Str::uuid();

        if (! $this->disk()->put($this->path($uploader, $id), $contents)) {
            throw new RuntimeException('The question import could not be stored.');
        }

        return $id;
    }

    /**
     * The contents of the uploader's import, or null when it does not exist.
     */
    public function get(User $uploader, string $id): ?string
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $path = $this->path($uploader, $id);

        return $this->disk()->exists($path) ? $this->disk()->get($path) : null;
    }

    public function delete(User $uploader, string $id): void
    {
        if (Str::isUuid($id)) {
            $this->disk()->delete($this->path($uploader, $id));
        }
    }

    public function path(User $uploader, string $id): string
    {
        return self::DIRECTORY.'/'.$uploader->organization_id.'/'.$uploader->getKey().'/'.$id.'.csv';
    }
}
