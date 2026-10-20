<?php

namespace App\Modules\Tenancy\Http\Requests;

use App\Modules\Tenancy\Actions\UpdateOrganizationSettings;
use App\Modules\Tenancy\Enums\GradingScale;
use App\Modules\Tenancy\Enums\Locale;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Support\SvgSanitizer;
use App\Modules\Tenancy\Support\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * UC-033: the organisation settings form. The subdomain is not a field: a
 * submitted subdomain is ignored (DATA-011).
 */
class UpdateOrganizationSettingsRequest extends FormRequest
{
    public const string ACCENT_COLOR_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->organization());
    }

    /**
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    public function rules(): array
    {
        $term = ['required', 'string', 'max:255'];

        return [
            'accent_color' => ['nullable', 'string', 'regex:'.self::ACCENT_COLOR_PATTERN],
            'student_term_el' => $term,
            'student_term_en' => $term,
            'teacher_term_el' => $term,
            'teacher_term_en' => $term,
            'grading_scale' => ['required', Rule::enum(GradingScale::class)],
            'leaderboard_enabled' => ['required', 'boolean'],
            'default_locale' => ['required', Rule::enum(Locale::class)],
            // SEC-010: png, jpg or sanitised svg up to 2 MB.
            'logo' => ['nullable', 'file', 'max:2048', 'mimes:png,jpg,jpeg,svg', 'extensions:png,jpg,jpeg,svg', $this->sanitizableSvg(...)],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accent_color.regex' => (string) __('organization.errors.accent_color'),
            'logo.mimes' => (string) __('organization.errors.logo_type'),
            'logo.extensions' => (string) __('organization.errors.logo_type'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'accent_color' => (string) __('organization.settings.accent_color'),
            'student_term_el' => (string) __('organization.settings.student_term_el'),
            'student_term_en' => (string) __('organization.settings.student_term_en'),
            'teacher_term_el' => (string) __('organization.settings.teacher_term_el'),
            'teacher_term_en' => (string) __('organization.settings.teacher_term_en'),
            'grading_scale' => (string) __('organization.settings.grading_scale'),
            'leaderboard_enabled' => (string) __('organization.settings.leaderboard'),
            'default_locale' => (string) __('organization.settings.default_locale'),
            'logo' => (string) __('organization.settings.logo'),
        ];
    }

    public function organization(): Organization
    {
        $organization = app(TenantContext::class)->current();
        abort_if($organization === null, 404);

        return $organization;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $settings = $this->safe()->only(UpdateOrganizationSettings::FIELDS);
        $settings['leaderboard_enabled'] = $this->boolean('leaderboard_enabled');

        return $settings;
    }

    public function logo(): ?UploadedFile
    {
        $logo = $this->file('logo');

        return $logo instanceof UploadedFile ? $logo : null;
    }

    public function removeLogo(): bool
    {
        return $this->boolean('remove_logo');
    }

    /**
     * An SVG logo must survive sanitising (well-formed, no DOCTYPE, <svg> root).
     */
    private function sanitizableSvg(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || $value->guessExtension() !== 'svg') {
            return;
        }

        if (app(SvgSanitizer::class)->sanitize((string) $value->get()) === null) {
            $fail((string) __('organization.errors.logo_svg'));
        }
    }
}
