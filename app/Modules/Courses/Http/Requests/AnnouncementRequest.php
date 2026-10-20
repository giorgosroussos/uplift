<?php

namespace App\Modules\Courses\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * UC-008: title and body of an announcement (ENT-Announcement).
 */
class AnnouncementRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => (string) __('courses.attributes.announcement_title'),
            'body' => (string) __('courses.attributes.announcement_body'),
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    public function announcement(): array
    {
        return [
            'title' => (string) $this->validated('title'),
            'body' => (string) $this->validated('body'),
        ];
    }
}
