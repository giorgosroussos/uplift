<?php

namespace App\Modules\Activities\Http\Requests;

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Activities\Support\Basics;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UC-022 step 1: course space, type and basics of a new activity. Only
 * registered activity types and course spaces of the current tenant are
 * accepted; ownership of the space is checked by the policy.
 */
class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $kinds = array_map(static fn (ActivityKind $kind): string => $kind->value, app(ActivityTypes::class)->kinds());

        return [
            'course_space_id' => [
                'required',
                'uuid',
                Rule::exists('course_spaces', 'id')->where('organization_id', app(TenantContext::class)->id()),
            ],
            'type' => ['required', 'string', Rule::in($kinds)],
            ...Basics::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Basics::attributes();
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::from((string) $this->validated('type'));
    }

    /**
     * @return array<string, mixed>
     */
    public function basics(): array
    {
        return $this->safe()->only(['title', 'description', 'deadline_at']);
    }
}
