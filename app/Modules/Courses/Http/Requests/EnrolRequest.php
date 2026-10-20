<?php

namespace App\Modules\Courses\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * UC-011: the enrolment key, when the course space asks for one.
 */
class EnrolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string|object>>
     */
    public function rules(): array
    {
        return [
            'enrolment_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'enrolment_key' => (string) __('courses.attributes.enrolment_key'),
        ];
    }

    public function enrolmentKey(): ?string
    {
        $key = $this->validated('enrolment_key');

        return is_string($key) ? $key : null;
    }
}
