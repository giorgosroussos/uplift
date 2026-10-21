<?php

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-023 and DATA-016: the teacher's personal question bank, its validation
| per question type, ordering of options and pairs, editing and deleting.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test']);

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
});

function bankUrl(string $path = ''): string
{
    return test()->tenantUrl(test()->acme, rtrim("question-bank/{$path}", '/'));
}

/**
 * A valid question of the type (DATA-016), with the given fields replaced.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function questionPayload(QuestionType $type = QuestionType::SingleChoice, array $overrides = []): array
{
    $base = ['type' => $type->value, 'prompt' => 'What is the capital of Greece?', 'points' => '2.50'];

    $items = match ($type) {
        QuestionType::SingleChoice => ['options' => [
            ['text' => 'Athens', 'is_correct' => true],
            ['text' => 'Sparta', 'is_correct' => false],
        ]],
        QuestionType::MultipleChoice => ['options' => [
            ['text' => 'Athens', 'is_correct' => true],
            ['text' => 'Thessaloniki', 'is_correct' => true],
            ['text' => 'Rome', 'is_correct' => false],
        ]],
        QuestionType::TrueFalse => ['options' => [
            ['text' => 'true', 'is_correct' => true],
            ['text' => 'false', 'is_correct' => false],
        ]],
        QuestionType::Matching => ['pairs' => [
            ['left_text' => 'Greece', 'right_text' => 'Athens'],
            ['left_text' => 'France', 'right_text' => 'Paris'],
        ]],
        QuestionType::FreeText => [],
    };

    return [...$base, ...$items, ...$overrides];
}

/**
 * @param  int  $count  number of options, the first one correct
 * @return list<array{text: string, is_correct: bool}>
 */
function choiceOptions(int $count, int $correct = 1): array
{
    return array_map(
        fn (int $i): array => ['text' => "Option {$i}", 'is_correct' => $i < $correct],
        range(0, $count - 1),
    );
}

describe('UC-023: creating questions', function () {
    it('saves a valid question of each type into the teacher\'s bank', function (QuestionType $type) {
        $this->actingAs($this->teacher)
            ->post(bankUrl(), questionPayload($type))
            ->assertSessionHasNoErrors()
            ->assertRedirect(bankUrl());

        $question = Question::query()->sole();

        expect($question->type)->toBe($type)
            ->and($question->owner_id)->toBe($this->teacher->id)
            ->and($question->organization_id)->toBe($this->acme->id)
            ->and($question->in_bank)->toBeTrue()
            ->and($question->points)->toBe('2.50')
            ->and($question->options()->count() + $question->pairs()->count())
            ->toBe(count(questionPayload($type)['options'] ?? questionPayload($type)['pairs'] ?? []));
    })->with(QuestionType::cases());

    it('stores the options of a choice question in the given order', function () {
        $this->actingAs($this->teacher)->post(bankUrl(), questionPayload(QuestionType::MultipleChoice));

        expect(ChoiceOption::query()->orderBy('position')->get(['text', 'is_correct', 'position'])->toArray())->toBe([
            ['text' => 'Athens', 'is_correct' => true, 'position' => 0],
            ['text' => 'Thessaloniki', 'is_correct' => true, 'position' => 1],
            ['text' => 'Rome', 'is_correct' => false, 'position' => 2],
        ]);
    });

    it('accepts 2 and 10 options, 2 and 10 pairs, and points of 0.01 and 100', function () {
        $valid = [
            questionPayload(QuestionType::SingleChoice, ['options' => choiceOptions(10)]),
            questionPayload(QuestionType::MultipleChoice, ['options' => choiceOptions(2, 2)]),
            questionPayload(QuestionType::Matching, ['pairs' => array_map(
                fn (int $i): array => ['left_text' => "L{$i}", 'right_text' => "R{$i}"],
                range(1, 10),
            )]),
            questionPayload(QuestionType::FreeText, ['points' => '0.01']),
            questionPayload(QuestionType::FreeText, ['points' => 100]),
        ];

        foreach ($valid as $payload) {
            $this->actingAs($this->teacher)->post(bankUrl(), $payload)->assertSessionHasNoErrors();
        }

        expect(Question::query()->count())->toBe(5);
    });
});

describe('UC-023 / DATA-016: refused questions', function () {
    /**
     * @param  array<string, mixed>  $payload
     */
    function assertQuestionRefused(array $payload, string $field): void
    {
        test()->actingAs(test()->teacher)
            ->from(bankUrl('create'))
            ->post(bankUrl(), $payload)
            ->assertRedirect(bankUrl('create'))
            ->assertSessionHasErrors($field);

        expect(Question::query()->count())->toBe(0)
            ->and(ChoiceOption::query()->count())->toBe(0)
            ->and(MatchingPair::query()->count())->toBe(0);
    }

    it('refuses a single-choice question with 2 correct options', function () {
        assertQuestionRefused(questionPayload(QuestionType::SingleChoice, ['options' => choiceOptions(3, 2)]), 'options');
    });

    it('refuses a single-choice question with no correct option', function () {
        assertQuestionRefused(questionPayload(QuestionType::SingleChoice, ['options' => choiceOptions(3, 0)]), 'options');
    });

    it('refuses a multiple-choice question with no correct option', function () {
        assertQuestionRefused(questionPayload(QuestionType::MultipleChoice, ['options' => choiceOptions(3, 0)]), 'options');
    });

    it('refuses a choice question with 1 or 11 options', function (QuestionType $type, int $count) {
        assertQuestionRefused(questionPayload($type, ['options' => choiceOptions($count)]), 'options');
    })->with([QuestionType::SingleChoice, QuestionType::MultipleChoice])->with([1, 11]);

    it('refuses a choice question without options', function () {
        assertQuestionRefused(questionPayload(QuestionType::SingleChoice, ['options' => []]), 'options');
    });

    it('refuses a choice option with an empty text', function () {
        assertQuestionRefused(questionPayload(QuestionType::SingleChoice, ['options' => [
            ['text' => 'Athens', 'is_correct' => true],
            ['text' => '', 'is_correct' => false],
        ]]), 'options.1.text');
    });

    it('refuses a true/false question without exactly 1 correct option', function (bool $first, bool $second) {
        assertQuestionRefused(questionPayload(QuestionType::TrueFalse, ['options' => [
            ['text' => 'true', 'is_correct' => $first],
            ['text' => 'false', 'is_correct' => $second],
        ]]), 'options');
    })->with([[true, true], [false, false]]);

    it('refuses a true/false question whose options are not exactly true and false', function (array $options) {
        $field = count($options) === 2 ? 'options.1.text' : 'options';

        assertQuestionRefused(questionPayload(QuestionType::TrueFalse, ['options' => $options]), $field);
    })->with([
        'translated text' => [[['text' => 'true', 'is_correct' => true], ['text' => 'Λάθος', 'is_correct' => false]]],
        'twice true' => [[['text' => 'true', 'is_correct' => true], ['text' => 'true', 'is_correct' => false]]],
        'three options' => [[['text' => 'true', 'is_correct' => true], ['text' => 'false', 'is_correct' => false], ['text' => 'maybe', 'is_correct' => false]]],
    ]);

    it('refuses a matching question with 1 pair or 11 pairs', function (int $count) {
        assertQuestionRefused(questionPayload(QuestionType::Matching, ['pairs' => array_map(
            fn (int $i): array => ['left_text' => "L{$i}", 'right_text' => "R{$i}"],
            range(1, $count),
        )]), 'pairs');
    })->with([1, 11]);

    it('refuses a matching pair with an empty side', function (string $side) {
        $pairs = [
            ['left_text' => 'Greece', 'right_text' => 'Athens'],
            ['left_text' => 'France', 'right_text' => 'Paris'],
        ];
        $pairs[1][$side] = '   ';

        assertQuestionRefused(questionPayload(QuestionType::Matching, ['pairs' => $pairs]), "pairs.1.{$side}");
    })->with(['left_text', 'right_text']);

    it('refuses a free-text question with options', function () {
        assertQuestionRefused(questionPayload(QuestionType::FreeText, ['options' => choiceOptions(2)]), 'options');
    });

    it('refuses a free-text question with pairs', function () {
        assertQuestionRefused(questionPayload(QuestionType::FreeText, ['pairs' => [
            ['left_text' => 'Greece', 'right_text' => 'Athens'],
            ['left_text' => 'France', 'right_text' => 'Paris'],
        ]]), 'pairs');
    });

    it('refuses pairs on a choice question and options on a matching question', function () {
        assertQuestionRefused(questionPayload(QuestionType::SingleChoice, ['pairs' => [
            ['left_text' => 'Greece', 'right_text' => 'Athens'],
            ['left_text' => 'France', 'right_text' => 'Paris'],
        ]]), 'pairs');
        assertQuestionRefused(questionPayload(QuestionType::Matching, ['options' => choiceOptions(2)]), 'options');
    });

    it('refuses points of 0, 100.01 or 1.005', function (mixed $points) {
        assertQuestionRefused(questionPayload(QuestionType::FreeText, ['points' => $points]), 'points');
    })->with([0, '0', '100.01', 100.01, '1.005', 1.005, '-1', 'abc', '']);

    it('refuses an unknown type and an empty prompt', function () {
        assertQuestionRefused(questionPayload(QuestionType::FreeText, ['type' => 'essay']), 'type');
        assertQuestionRefused(questionPayload(QuestionType::FreeText, ['prompt' => '']), 'prompt');
    });

    it('shows the reason in the teacher\'s language', function () {
        $this->teacher->forceFill(['locale' => 'el'])->save();

        $this->actingAs($this->teacher)
            ->post(bankUrl(), questionPayload(QuestionType::SingleChoice, ['options' => choiceOptions(3, 2)]))
            ->assertSessionHasErrors(['options' => 'Σημειώστε ακριβώς μία επιλογή ως σωστή.']);
    });
});

describe('UC-023 / DATA-016: true/false questions', function () {
    it('stores the option texts as true and false and hands them to the page untranslated', function () {
        $this->actingAs($this->teacher)->post(bankUrl(), questionPayload(QuestionType::TrueFalse, ['options' => [
            ['text' => 'true', 'is_correct' => false],
            ['text' => 'false', 'is_correct' => true],
        ]]))->assertSessionHasNoErrors();

        $question = Question::query()->sole();

        expect($question->options->pluck('text')->all())->toBe(['true', 'false'])
            ->and($question->options->pluck('is_correct')->all())->toBe([false, true]);

        foreach (['en', 'el'] as $locale) {
            $this->teacher->forceFill(['locale' => $locale])->save();

            $this->actingAs($this->teacher)
                ->get(bankUrl("{$question->id}/edit"))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Assessment/QuestionBank/Edit')
                    ->where('question.type', 'true_false')
                    ->where('question.options.0.text', 'true')
                    ->where('question.options.1.text', 'false'));
        }
    });

    it('translates the labels true and false in both UI languages', function () {
        expect(trans('assessment.true_false.true', [], 'en'))->toBe('True')
            ->and(trans('assessment.true_false.false', [], 'en'))->toBe('False')
            ->and(trans('assessment.true_false.true', [], 'el'))->toBe('Σωστό')
            ->and(trans('assessment.true_false.false', [], 'el'))->toBe('Λάθος');
    });
});

describe('UC-023: the bank list', function () {
    it('lists only the teacher\'s own bank questions, newest first', function () {
        $older = Question::factory()->for($this->teacher, 'owner')->create(['prompt' => 'Older']);
        $this->travel(1)->minutes();
        $newer = Question::factory()->for($this->teacher, 'owner')->matching()->create(['prompt' => 'Newer']);
        Question::factory()->for($this->teacher, 'owner')->quizCopy()->create(['prompt' => 'Quiz copy']);
        Question::factory()->create(['prompt' => 'Of another teacher']);

        $this->actingAs($this->teacher)
            ->get(bankUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assessment/QuestionBank/Index')
                ->has('questions.data', 2)
                ->where('questions.data.0.id', $newer->id)
                ->where('questions.data.0.type', 'matching')
                ->where('questions.data.0.items_count', 2)
                ->where('questions.data.1.id', $older->id)
                ->where('questions.data.1.points', '1.00'));
    });

    it('opens the create page for a teacher', function () {
        $this->actingAs($this->teacher)
            ->get(bankUrl('create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Assessment/QuestionBank/Create'));
    });
});

describe('UC-023: editing questions', function () {
    beforeEach(function () {
        $this->question = Question::factory()->for($this->teacher, 'owner')
            ->withOptions([['A', true], ['B', false], ['C', false]])
            ->create(['prompt' => 'Pick A']);
        $this->bankOptions = $this->question->options()->get();
    });

    it('keeps the new order after the teacher reorders the options and saves', function () {
        [$a, $b, $c] = $this->bankOptions->all();

        $this->actingAs($this->teacher)->put(bankUrl($this->question->id), questionPayload(QuestionType::SingleChoice, [
            'prompt' => 'Pick A',
            'options' => [
                ['id' => $c->id, 'text' => 'C', 'is_correct' => false],
                ['id' => $a->id, 'text' => 'A', 'is_correct' => true],
                ['id' => $b->id, 'text' => 'B', 'is_correct' => false],
            ],
        ]))->assertSessionHasNoErrors();

        expect($this->question->options()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id])
            ->and($this->question->options()->pluck('position')->all())->toBe([0, 1, 2]);

        $this->actingAs($this->teacher)
            ->get(bankUrl("{$this->question->id}/edit"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('question.options.0.text', 'C')
                ->where('question.options.1.text', 'A')
                ->where('question.options.2.text', 'B'));
    });

    it('keeps the new order of matching pairs', function () {
        $question = Question::factory()->for($this->teacher, 'owner')->matching()->create();
        [$first, $second] = $question->pairs()->get()->all();

        $this->actingAs($this->teacher)->put(bankUrl($question->id), questionPayload(QuestionType::Matching, ['pairs' => [
            ['id' => $second->id, 'left_text' => $second->left_text, 'right_text' => $second->right_text],
            ['id' => $first->id, 'left_text' => $first->left_text, 'right_text' => $first->right_text],
        ]]))->assertSessionHasNoErrors();

        expect($question->pairs()->pluck('id')->all())->toBe([$second->id, $first->id]);
    });

    it('updates the question, adds and removes options', function () {
        [$a, $b] = $this->bankOptions->all();

        $this->actingAs($this->teacher)->put(bankUrl($this->question->id), [
            'type' => 'multiple_choice',
            'prompt' => 'Pick the vowels',
            'points' => '3',
            'options' => [
                ['id' => $a->id, 'text' => 'A', 'is_correct' => true],
                ['id' => null, 'text' => 'E', 'is_correct' => true],
                ['id' => $b->id, 'text' => 'B', 'is_correct' => false],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(bankUrl());

        $question = $this->question->fresh();

        expect($question?->type)->toBe(QuestionType::MultipleChoice)
            ->and($question?->prompt)->toBe('Pick the vowels')
            ->and($question?->points)->toBe('3.00')
            ->and($question?->options->pluck('text')->all())->toBe(['A', 'E', 'B'])
            ->and(ChoiceOption::query()->whereKey($this->bankOptions[2]->id)->exists())->toBeFalse();
    });

    it('drops the options when the question becomes free text', function () {
        $this->actingAs($this->teacher)
            ->put(bankUrl($this->question->id), questionPayload(QuestionType::FreeText))
            ->assertSessionHasNoErrors();

        expect(ChoiceOption::query()->count())->toBe(0)
            ->and($this->question->fresh()?->type)->toBe(QuestionType::FreeText);
    });

    it('does not adopt an option of another question by its id', function () {
        $other = Question::factory()->for($this->teacher, 'owner')->create();
        $foreign = $other->options()->firstOrFail();

        $this->actingAs($this->teacher)->put(bankUrl($this->question->id), questionPayload(QuestionType::SingleChoice, [
            'options' => [
                ['id' => $foreign->id, 'text' => 'Hijacked', 'is_correct' => true],
                ['text' => 'B', 'is_correct' => false],
            ],
        ]))->assertSessionHasNoErrors();

        expect($foreign->fresh()?->text)->toBe('A')
            ->and($foreign->fresh()?->question_id)->toBe($other->id)
            ->and($this->question->options()->pluck('text')->all())->toBe(['Hijacked', 'B']);
    });

    it('refuses an invalid edit and changes nothing', function () {
        $before = $this->question->options()->get(['id', 'text', 'is_correct', 'position'])->toArray();

        $this->actingAs($this->teacher)
            ->put(bankUrl($this->question->id), questionPayload(QuestionType::SingleChoice, ['options' => choiceOptions(3, 2)]))
            ->assertSessionHasErrors('options');

        expect($this->question->fresh()?->prompt)->toBe('Pick A')
            ->and($this->question->options()->get(['id', 'text', 'is_correct', 'position'])->toArray())->toBe($before);
    });

    it('leaves a quiz copy unchanged when the bank question is edited', function () {
        $copy = Question::factory()->for($this->teacher, 'owner')->quizCopy()
            ->withOptions([['A', true], ['B', false], ['C', false]])
            ->create(['prompt' => 'Pick A']);

        $this->actingAs($this->teacher)
            ->put(bankUrl($this->question->id), questionPayload(QuestionType::FreeText, ['prompt' => 'Changed']))
            ->assertSessionHasNoErrors();

        expect($copy->fresh()?->prompt)->toBe('Pick A')
            ->and($copy->options()->pluck('text')->all())->toBe(['A', 'B', 'C']);
    });
});

describe('UC-023: deleting questions', function () {
    it('removes a deleted question from the bank, with its options', function () {
        $question = Question::factory()->for($this->teacher, 'owner')->create();
        $kept = Question::factory()->for($this->teacher, 'owner')->matching()->create();

        $this->actingAs($this->teacher)
            ->delete(bankUrl($question->id))
            ->assertRedirect(bankUrl());

        expect(Question::query()->whereKey($question->id)->exists())->toBeFalse()
            ->and(ChoiceOption::query()->where('question_id', $question->id)->count())->toBe(0)
            ->and(Question::query()->pluck('id')->all())->toBe([$kept->id]);

        $this->actingAs($this->teacher)
            ->get(bankUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->has('questions.data', 1)
                ->where('questions.data.0.id', $kept->id));
    });

    it('leaves a quiz copy in place when the bank question is deleted', function () {
        $question = Question::factory()->for($this->teacher, 'owner')->create();
        $copy = Question::factory()->for($this->teacher, 'owner')->quizCopy()->create();

        $this->actingAs($this->teacher)->delete(bankUrl($question->id));

        expect($copy->fresh())->not->toBeNull()
            ->and($copy->options()->count())->toBe(2);
    });
});
