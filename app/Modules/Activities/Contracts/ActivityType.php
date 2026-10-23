<?php

namespace App\Modules\Activities\Contracts;

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\SubmissionStatus;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\Submission;
use Illuminate\Validation\ValidationException;

/**
 * Type-specific behaviour of one of the five activity types (ARCH-004).
 *
 * A module adds an activity type by implementing this contract and
 * registering it in its service provider, never by editing Activities:
 *
 *     $this->callAfterResolving(ActivityTypes::class, fn (ActivityTypes $types) => $types->register(QuizType::class));
 *
 * Activities owns the base row (ENT-Activity), its lifecycle and the wizard
 * steps basics and review; the type owns its subtype row (same id as the
 * activity, DATA-001) and the wizard steps settings and content. Activities
 * authorises every call (only the owning teacher reaches the wizard) and
 * runs saves in a transaction.
 */
interface ActivityType
{
    /**
     * The ENT-Activity type this implementation handles.
     */
    public function kind(): ActivityKind;

    /**
     * Vue page component (path under resources/js/pages, without .vue) that
     * renders the given wizard step, or null when the type has no such
     * step. Only WizardStep::Settings and WizardStep::Content are asked;
     * a type without settings of its own (topic selection) has only a
     * content step. The component gets the props
     * `modelValue` (the step data), `errors` (field => message),
     * `activity` and `locked` (grading locked) and emits `update:modelValue`.
     */
    public function component(WizardStep $step): ?string;

    /**
     * Saved data of a wizard step (settings or content), or null when the
     * step was never saved, so the wizard can restore it and knows which
     * steps are complete.
     *
     * @return array<string, mixed>|null
     */
    public function stepData(Activity $activity, WizardStep $step): ?array;

    /**
     * Laravel validation rules of a wizard step (settings or content).
     *
     * @return array<string, mixed>
     */
    public function rules(Activity $activity, WizardStep $step): array;

    /**
     * Stores the validated data of a wizard step (settings or content).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException for rules that depend on stored state
     */
    public function save(Activity $activity, WizardStep $step, array $data): void;

    /**
     * Whether storing the given step data would change how existing
     * submissions are graded (for a quiz: points, correct answers, negative
     * marking, ...). Activities refuses such a change once the activity has
     * submissions (UC-022).
     *
     * @param  array<string, mixed>  $data
     */
    public function changesGrading(Activity $activity, WizardStep $step, array $data): bool;

    /**
     * Why the activity cannot be published yet, as field => translated
     * message; empty when it can be published (UC-022: every required field
     * must be valid; e.g. a quiz needs at least one question).
     *
     * @return array<string, string>
     */
    public function publishErrors(Activity $activity): array;

    /**
     * Whether anyone has submitted to the activity.
     */
    public function hasSubmissions(Activity $activity): bool;

    /**
     * The submissions overview of the activity (UC-028), optionally only
     * those with the given status.
     *
     * @return list<Submission>
     */
    public function submissions(Activity $activity, ?SubmissionStatus $status): array;
}
