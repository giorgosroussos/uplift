<?php

namespace App\Modules\Assessment;

use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Policies\QuestionPolicy;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

/**
 * Questions, the personal question bank (UC-023) and, later, quizzes.
 */
class AssessmentServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Gate::policy(Question::class, QuestionPolicy::class);
    }
}
