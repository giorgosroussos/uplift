<?php

use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Support\QuestionCsv;
use App\Modules\Assessment\Support\QuestionImportFiles;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
| Browser flows of UC-030: a teacher opens the import page, reviews the
| per-row report of a pending import and imports the valid rows; with no
| valid row the confirm button is disabled.
|
| The browser test server always answers as http://127.0.0.1:<port>, so the
| organisation "127" on the apex domain "0.0.1" stands in for a subdomain.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => '0.0.1', 'app.locale' => 'en', 'assessment.question_import_disk' => 'local']);
    Storage::fake('local');

    $this->organization = $this->createOrganization(['subdomain' => '127', 'name' => 'Acme School']);
    $this->actingAsTenant($this->organization);
    $this->teacher = User::factory()->teacher()->create(['locale' => 'en']);
});

/**
 * The CSV contents with the header and the given rows (cells in header
 * order).
 *
 * @param  list<list<string>>  $rows
 */
function browserCsv(array $rows): string
{
    $width = count(QuestionCsv::headers());
    $lines = [implode(',', QuestionCsv::headers())];

    foreach ($rows as $row) {
        $lines[] = implode(',', array_pad($row, $width, ''));
    }

    return implode("\n", $lines)."\n";
}

/**
 * Stores a pending import of the teacher as an upload would and returns
 * its id. The browser test server does not pass multipart file uploads to
 * the application, so the upload itself is covered by
 * tests/Feature/Assessment/QuestionImportTest.php.
 */
function pendingImport(User $teacher, string $contents): string
{
    return app(QuestionImportFiles::class)->put($teacher, $contents);
}

it('leads a teacher to the import page with the template and format', function () {
    $this->actingAs($this->teacher);

    visit('/question-bank')
        ->click('@import-questions')
        ->assertPathIs('/question-bank/import')
        ->assertSee('Download the template')
        ->assertSee('File format')
        ->assertDisabled('@import-upload')
        ->assertNoJavaScriptErrors();
});

it('lets a teacher review the report and import the valid rows', function () {
    $this->actingAs($this->teacher);

    $id = pendingImport($this->teacher, browserCsv([
        ['single_choice', 'Capital of Greece?', '2', '2', 'Sparta', 'Athens'],
        ['free_text', 'Explain photosynthesis.', '0'],
        ['true_false', 'The earth is round.', '1', 'true'],
    ]));

    visit("/question-bank/import/{$id}")
        ->assertSee('Import report')
        ->assertSee('2 valid rows, 1 rows with errors.')
        ->assertSee('Points must be more than 0 and at most 100.')
        ->assertEnabled('@import-confirm')
        ->click('@import-confirm')
        ->assertPathIs('/question-bank')
        ->assertSee('Capital of Greece?')
        ->assertSee('The earth is round.')
        ->assertNoJavaScriptErrors();

    expect(Question::query()->pluck('prompt')->sort()->values()->all())
        ->toBe(['Capital of Greece?', 'The earth is round.'])
        ->and(Storage::disk('local')->allFiles('question-imports'))->toBe([]);
});

it('disables confirm when no row is valid and imports nothing on cancel', function () {
    $this->actingAs($this->teacher);

    $id = pendingImport($this->teacher, browserCsv([['essay', 'Unknown type', '1']]));

    visit("/question-bank/import/{$id}")
        ->assertSee('Unknown type')
        ->assertSee('No row is valid, so there is nothing to import.')
        ->assertDisabled('@import-confirm')
        ->click('@import-cancel')
        ->assertPathIs('/question-bank')
        ->assertNoJavaScriptErrors();

    expect(Question::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('question-imports'))->toBe([]);
});
