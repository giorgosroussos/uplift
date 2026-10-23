<?php

namespace App\Modules\Activities\Actions;

use App\Modules\Activities\Enums\ActivityKind;
use App\Modules\Activities\Enums\WizardStep;
use App\Modules\Activities\Models\Activity;
use App\Modules\Activities\Support\ActivityTypes;
use App\Modules\Activities\Support\Basics;
use App\Modules\Courses\Enums\CourseSpaceStatus;
use App\Modules\Courses\Models\CourseSpace;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * UC-022: the wizard of a new or an existing activity. Everything saved so
 * far is restored from the database: the basics from the activity, the
 * settings and content from its type. The wizard opens at the requested
 * step, else at the first step not completed yet.
 */
class ShowWizard
{
    public function __construct(private readonly ActivityTypes $types) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $teacher, ?Activity $activity, ?string $requestedStep = null, ?string $courseSpaceId = null, ?string $kind = null): array
    {
        $steps = $this->steps($activity);
        $available = array_column($steps, 'key');

        $current = in_array($requestedStep, $available, true) && ($activity !== null || $requestedStep === WizardStep::Basics->value)
            ? $requestedStep
            : $this->firstIncomplete($steps);

        $type = $activity !== null && $this->types->has($activity->type) ? $this->types->for($activity) : null;
        $gate = Gate::forUser($teacher);

        return [
            'activity' => $activity === null ? null : [
                'id' => $activity->id,
                'type' => $activity->type->value,
                'status' => $activity->status->value,
                'course_space_id' => $activity->course_space_id,
                'course_space_title' => $activity->courseSpace->title,
                'published_at' => $activity->published_at?->toIso8601String(),
            ],
            'basics' => $activity === null
                ? ['course_space_id' => $courseSpaceId, 'type' => $kind, 'title' => '', 'description' => null, 'deadline_at' => null, 'is_group' => false, 'group_size' => null]
                : ['course_space_id' => $activity->course_space_id, 'type' => $activity->type->value, ...Basics::of($activity)],
            'data' => [
                WizardStep::Settings->value => $activity !== null && $type !== null ? $type->stepData($activity, WizardStep::Settings) : null,
                WizardStep::Content->value => $activity !== null && $type !== null ? $type->stepData($activity, WizardStep::Content) : null,
            ],
            'steps' => $steps,
            'step' => $current,
            'gradingLocked' => $activity !== null && $type !== null && ! $activity->isDraft() && $type->hasSubmissions($activity),
            'types' => array_map(static fn (ActivityKind $registered): string => $registered->value, $this->types->kinds()),
            'courseSpaces' => $activity === null ? $this->courseSpaces($teacher) : [],
            'can' => [
                'publish' => $activity !== null && $gate->allows('publish', $activity),
                'close' => $activity !== null && $gate->allows('close', $activity),
            ],
        ];
    }

    /**
     * The wizard steps of the activity with the Vue component of each
     * type-owned step and whether it has been completed.
     *
     * @return list<array{key: string, component: string|null, complete: bool}>
     */
    public function steps(?Activity $activity): array
    {
        $steps = [['key' => WizardStep::Basics->value, 'component' => null, 'complete' => $activity !== null]];
        $type = $activity !== null && $this->types->has($activity->type) ? $this->types->for($activity) : null;

        if ($activity !== null && $type !== null) {
            foreach ([WizardStep::Settings, WizardStep::Content] as $step) {
                $component = $type->component($step);

                if ($component !== null) {
                    $steps[] = [
                        'key' => $step->value,
                        'component' => $component,
                        'complete' => $type->stepData($activity, $step) !== null,
                    ];
                }
            }
        }

        $steps[] = ['key' => WizardStep::Review->value, 'component' => null, 'complete' => false];

        return $steps;
    }

    /**
     * @param  list<array{key: string, component: string|null, complete: bool}>  $steps
     */
    private function firstIncomplete(array $steps): string
    {
        foreach ($steps as $step) {
            if (! $step['complete']) {
                return $step['key'];
            }
        }

        return WizardStep::Review->value;
    }

    /**
     * Active course spaces the teacher owns, for a new activity.
     *
     * @return list<array{id: string, title: string}>
     */
    private function courseSpaces(User $teacher): array
    {
        return CourseSpace::query()
            ->where('teacher_id', $teacher->getKey())
            ->where('status', CourseSpaceStatus::Active)
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (CourseSpace $space): array => ['id' => $space->id, 'title' => $space->title])
            ->values()
            ->all();
    }
}
