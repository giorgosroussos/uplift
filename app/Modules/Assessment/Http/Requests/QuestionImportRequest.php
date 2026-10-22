<?php

namespace App\Modules\Assessment\Http\Requests;

use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Support\QuestionCsv;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * UC-030, DATA-017 and SEC-010: the upload of a question CSV of at most
 * 1 MB. Its contents are checked by Actions/StoreQuestionImport.
 */
class QuestionImportRequest extends FormRequest
{
    /**
     * OWN-016: only an active teacher imports into their own bank.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Question::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.intdiv(QuestionCsv::MAX_BYTES, 1024), 'extensions:csv,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => (string) __('assessment.import.errors.file_required'),
            'file.file' => (string) __('assessment.import.errors.file_required'),
            'file.uploaded' => (string) __('assessment.import.refused.too_large'),
            'file.max' => (string) __('assessment.import.refused.too_large'),
            'file.extensions' => (string) __('assessment.import.errors.file_type'),
        ];
    }

    public function csv(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }
}
