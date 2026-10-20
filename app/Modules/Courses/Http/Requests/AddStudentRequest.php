<?php

namespace App\Modules\Courses\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * UC-021: the student the owning teacher adds to the course space.
 */
class AddStudentRequest extends FormRequest
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
            'student_id' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_id' => (string) __('courses.attributes.student'),
        ];
    }

    public function studentId(): string
    {
        return (string) $this->validated('student_id');
    }
}
