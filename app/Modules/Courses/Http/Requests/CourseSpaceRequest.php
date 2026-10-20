<?php

namespace App\Modules\Courses\Http\Requests;

use App\Modules\Courses\Enums\EnrolmentPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC-021: title, description and enrolment policy of a course space; the
 * enrolment key is required when the policy is key.
 */
class CourseSpaceRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'enrolment_policy' => ['required', Rule::enum(EnrolmentPolicy::class)],
            'enrolment_key' => ['nullable', 'required_if:enrolment_policy,key', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => (string) __('courses.attributes.title'),
            'description' => (string) __('courses.attributes.description'),
            'enrolment_policy' => (string) __('courses.attributes.enrolment_policy'),
            'enrolment_key' => (string) __('courses.attributes.enrolment_key'),
        ];
    }

    /**
     * @return array{title: string, description: string|null, enrolment_policy: string, enrolment_key: string|null}
     */
    public function courseSpace(): array
    {
        $policy = (string) $this->validated('enrolment_policy');

        return [
            'title' => (string) $this->validated('title'),
            'description' => $this->validated('description'),
            'enrolment_policy' => $policy,
            'enrolment_key' => $policy === EnrolmentPolicy::Key->value ? $this->validated('enrolment_key') : null,
        ];
    }
}
