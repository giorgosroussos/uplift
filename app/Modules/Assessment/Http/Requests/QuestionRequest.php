<?php

namespace App\Modules\Assessment\Http\Requests;

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Support\QuestionRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * UC-023 and DATA-016: a bank question of one of the five types, validated
 * by Support/QuestionRules.
 */
class QuestionRequest extends FormRequest
{
    /**
     * OWN-016 before validation, so a refused actor learns nothing from
     * validation errors.
     */
    public function authorize(): bool
    {
        $question = $this->route('question');
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $question instanceof Question
            ? $user->can('update', $question)
            : $user->can('create', Question::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string|object>>
     */
    public function rules(): array
    {
        return QuestionRules::rules($this->input('type'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return QuestionRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return QuestionRules::attributes();
    }

    /**
     * @return array{type: QuestionType, prompt: string, points: string, options: list<array{id: string|null, text: string, is_correct: bool}>, pairs: list<array{id: string|null, left_text: string, right_text: string}>}
     */
    public function question(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return QuestionRules::normalize($validated);
    }
}
