<?php

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Models\Question;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Browser flows of UC-023: a teacher builds a question, orders its options
| by drag and drop, sees true/false options in their UI language, and
| deletes a question from the bank.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en']);

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
    $this->actingAsTenant($this->organization);
    $this->teacher = User::factory()->teacher()->create(['locale' => 'en']);
});

it('lets a teacher create a choice question and keeps the order set by drag and drop', function () {
    $this->actingAs($this->teacher);

    visit('/question-bank')
        ->assertSee('Your question bank is empty.')
        ->click('@create-question')
        ->assertPathIs('/question-bank/create')
        ->fill('@question-prompt', 'Which city is the capital of Greece?')
        ->fill('@question-points', '2.5')
        ->fill('@option-text-0', 'Sparta')
        ->fill('@option-text-1', 'Thebes')
        ->click('@add-option')
        ->fill('@option-text-2', 'Athens')
        ->click('@option-correct-2')
        ->drag('@option-handle-2', '@option-handle-0')
        ->assertValue('@option-text-0', 'Athens')
        ->click('@question-submit')
        ->assertPathIs('/question-bank')
        ->assertSee('Which city is the capital of Greece?')
        ->assertNoJavaScriptErrors();

    $question = Question::query()->sole();

    expect($question->owner_id)->toBe($this->teacher->id)
        ->and($question->points)->toBe('2.50')
        ->and($question->options->pluck('text')->all())->toBe(['Athens', 'Sparta', 'Thebes'])
        ->and($question->options->pluck('is_correct')->all())->toBe([true, false, false]);
});

it('shows the reason when a question breaks a rule and saves nothing', function () {
    $this->actingAs($this->teacher);

    visit('/question-bank/create')
        ->fill('@question-prompt', 'Pick the vowels')
        ->click('@question-type-multiple_choice')
        ->fill('@option-text-0', 'A')
        ->fill('@option-text-1', 'B')
        ->click('@question-submit')
        ->assertSee('Mark at least one option as correct.')
        ->assertNoJavaScriptErrors();

    expect(Question::query()->count())->toBe(0);
});

it('shows true/false options in the teacher\'s UI language while storing true and false', function (string $locale, string $true, string $false) {
    $this->teacher->forceFill(['locale' => $locale])->save();
    $question = Question::factory()->for($this->teacher, 'owner')->trueFalse()->create();
    $this->actingAs($this->teacher);

    visit("/question-bank/{$question->id}/edit")
        ->assertSeeIn('@option-label-0', $true)
        ->assertSeeIn('@option-label-1', $false)
        ->assertNoJavaScriptErrors();

    expect($question->options()->pluck('text')->all())->toBe(['true', 'false']);
})->with([
    'English UI' => ['en', 'True', 'False'],
    'Greek UI' => ['el', 'Σωστό', 'Λάθος'],
]);

it('creates a true/false question from the builder with the stored texts true and false', function () {
    $this->actingAs($this->teacher);

    visit('/question-bank/create')
        ->fill('@question-prompt', 'The earth is flat.')
        ->click('@question-type-true_false')
        ->assertSeeIn('@option-label-0', 'True')
        ->click('@option-correct-1')
        ->click('@question-submit')
        ->assertPathIs('/question-bank')
        ->assertNoJavaScriptErrors();

    $question = Question::query()->sole();

    expect($question->type)->toBe(QuestionType::TrueFalse)
        ->and($question->options->pluck('text')->all())->toBe(['true', 'false'])
        ->and($question->options->pluck('is_correct')->all())->toBe([false, true]);
});

it('lets a teacher delete a question from the bank', function () {
    Question::factory()->for($this->teacher, 'owner')->create(['prompt' => 'Old question']);
    $this->actingAs($this->teacher);

    visit('/question-bank')
        ->assertSee('Old question')
        ->click('@bank-question-delete')
        ->assertVisible('@delete-question-dialog')
        ->click('@delete-question-confirm')
        ->assertSee('Your question bank is empty.')
        ->assertNoJavaScriptErrors();

    expect(Question::query()->count())->toBe(0);
});
