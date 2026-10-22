<?php

use App\Modules\Assessment\Enums\QuestionType;
use App\Modules\Assessment\Models\ChoiceOption;
use App\Modules\Assessment\Models\MatchingPair;
use App\Modules\Assessment\Models\Question;
use App\Modules\Assessment\Support\QuestionCsv;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
| UC-030 and DATA-017: bulk import of questions from a CSV file into the
| teacher's own bank: upload, per-row report, confirm or cancel, the file
| lifecycle and OWN-016 for imports.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['tenancy.domain' => 'uplift.test', 'assessment.question_import_disk' => 'local']);
    Storage::fake('local');

    $this->acme = $this->createOrganization(['subdomain' => 'acme']);
    $this->actingAsTenant($this->acme);

    $this->teacher = User::factory()->teacher()->create();
});

function importUrl(string $path = '', ?Organization $organization = null): string
{
    return test()->tenantUrl($organization ?? test()->acme, rtrim("question-bank/import/{$path}", '/'));
}

/**
 * @param  list<string>  $lines
 */
function csvFile(array $lines, string $name = 'questions.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, implode("\r\n", $lines)."\r\n");
}

function csvHeader(string $delimiter = ','): string
{
    return implode($delimiter, QuestionCsv::headers());
}

/**
 * One CSV line with the given cells in header order (type, prompt, points,
 * correct, option_1 ...), padded to all columns.
 *
 * @param  list<string>  $cells
 */
function csvRow(array $cells, string $delimiter = ','): string
{
    $cells = array_pad($cells, count(QuestionCsv::headers()), '');

    return implode($delimiter, array_map(
        fn (string $cell): string => str_contains($cell, $delimiter) || str_contains($cell, '"') ? '"'.str_replace('"', '""', $cell).'"' : $cell,
        $cells,
    ));
}

/**
 * @return list<string>
 */
function oneRowPerType(): array
{
    return [
        csvHeader(),
        csvRow(['single_choice', 'Capital of Greece?', '2', '2', 'Sparta', 'Athens', 'Thebes']),
        csvRow(['multiple_choice', 'Prime numbers?', '3.5', '1|3', '2', '4', '7']),
        csvRow(['true_false', 'The earth is round.', '1', 'false']),
        csvRow(['matching', 'Match countries and capitals', '4', '', 'Greece => Athens', 'France => Paris']),
        csvRow(['free_text', 'Explain photosynthesis.', '10']),
    ];
}

/**
 * Uploads the file as the actor and returns the import id.
 */
function uploadImport(User $actor, UploadedFile $file): string
{
    $response = test()->actingAs($actor)->post(importUrl(), ['file' => $file]);
    $response->assertSessionHasNoErrors()->assertRedirect();

    $id = Str::afterLast(rtrim((string) $response->headers->get('Location'), '/'), '/');
    expect(Str::isUuid($id))->toBeTrue();

    return $id;
}

function importPath(User $uploader, string $id): string
{
    return "question-imports/{$uploader->organization_id}/{$uploader->id}/{$id}.csv";
}

/**
 * @return array<string, mixed>
 */
function importSnapshot(): array
{
    return [
        'questions' => Question::query()->withoutGlobalScopes()->orderBy('id')->get(['id', 'owner_id', 'prompt'])->toArray(),
        'options' => ChoiceOption::query()->withoutGlobalScopes()->count(),
        'pairs' => MatchingPair::query()->withoutGlobalScopes()->count(),
    ];
}

describe('UC-030: a valid file', function () {
    it('imports one row of each of the five types into the teacher\'s bank with options, flags, pairs and points', function () {
        $id = uploadImport($this->teacher, csvFile(oneRowPerType()));

        Storage::disk('local')->assertExists(importPath($this->teacher, $id));

        $this->actingAs($this->teacher)->get(importUrl($id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assessment/QuestionBank/ImportReport')
                ->where('report.refusal', null)
                ->where('report.valid_count', 5)
                ->where('report.invalid_count', 0)
                ->where('report.rows.0.line', 2)
                ->where('report.rows.0.valid', true)
                ->where('report.rows.4.line', 6));

        $this->actingAs($this->teacher)->post(importUrl("{$id}/confirm"))
            ->assertSessionHasNoErrors()
            ->assertRedirect(test()->tenantUrl($this->acme, 'question-bank'));

        $questions = Question::query()->with(['options', 'pairs'])->get()->keyBy(fn (Question $q) => $q->type->value);

        expect($questions)->toHaveCount(5)
            ->and($questions->every(fn (Question $q) => $q->owner_id === $this->teacher->id && $q->in_bank === true && $q->organization_id === $this->acme->id))->toBeTrue();

        $single = $questions['single_choice'];
        expect($single->prompt)->toBe('Capital of Greece?')
            ->and($single->points)->toBe('2.00')
            ->and($single->options->map(fn ($o) => [$o->text, $o->is_correct, $o->position])->all())
            ->toBe([['Sparta', false, 0], ['Athens', true, 1], ['Thebes', false, 2]]);

        expect($questions['multiple_choice']->points)->toBe('3.50')
            ->and($questions['multiple_choice']->options->map(fn ($o) => [$o->text, $o->is_correct])->all())
            ->toBe([['2', true], ['4', false], ['7', true]]);

        expect($questions['true_false']->options->map(fn ($o) => [$o->text, $o->is_correct])->all())
            ->toBe([['true', false], ['false', true]]);

        expect($questions['matching']->points)->toBe('4.00')
            ->and($questions['matching']->options)->toHaveCount(0)
            ->and($questions['matching']->pairs->map(fn ($p) => [$p->left_text, $p->right_text, $p->position])->all())
            ->toBe([['Greece', 'Athens', 0], ['France', 'Paris', 1]]);

        expect($questions['free_text']->points)->toBe('10.00')
            ->and($questions['free_text']->options)->toHaveCount(0)
            ->and($questions['free_text']->pairs)->toHaveCount(0);

        Storage::disk('local')->assertMissing(importPath($this->teacher, $id));
    });

    it('accepts a \';\'-delimited file with a UTF-8 BOM and points with a decimal comma', function () {
        $lines = [
            "\xEF\xBB\xBF".csvHeader(';'),
            csvRow(['single_choice', 'Πρωτεύουσα της Ελλάδας;', '2,5', '1', 'Αθήνα', 'Σπάρτη'], ';'),
            csvRow(['free_text', 'Περιγράψτε τη φωτοσύνθεση.', '2,5'], ';'),
        ];

        $id = uploadImport($this->teacher, csvFile($lines));

        $this->actingAs($this->teacher)->get(importUrl($id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.valid_count', 2)
                ->where('report.rows.0.valid', true)
                ->where('report.rows.0.prompt', 'Πρωτεύουσα της Ελλάδας;')
                ->where('report.rows.1.valid', true));

        $this->actingAs($this->teacher)->post(importUrl("{$id}/confirm"))->assertSessionHasNoErrors();

        expect(Question::query()->pluck('points')->all())->toBe(['2.50', '2.50'])
            ->and(Question::query()->where('type', 'single_choice')->sole()->options->pluck('text')->all())->toBe(['Αθήνα', 'Σπάρτη']);
    });

    it('accepts the header columns in any order and quoted cells with delimiters and line breaks', function () {
        $lines = [
            'points,type,correct,prompt,'.implode(',', array_map(fn ($n) => "option_{$n}", range(1, 10))),
            '1,single_choice,1,"Which one, really?","A, the first",B,,,,,,,,',
            '2,free_text,,"Two',
            'lines",,,,,,,,,,',
            '3,free_text,,After the multi-line row,,,,,,,,,,',
        ];

        $id = uploadImport($this->teacher, csvFile($lines));

        $this->actingAs($this->teacher)->get(importUrl($id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.valid_count', 3)
                ->where('report.rows.0.prompt', 'Which one, really?')
                ->where('report.rows.1.line', 3)
                ->where('report.rows.2.line', 5));
    });
});

describe('UC-030: invalid rows', function () {
    it('reports the line number and error of an invalid row, then imports the 3 valid rows and skips it', function () {
        $lines = [
            csvHeader(),
            csvRow(['single_choice', 'Valid one', '1', '1', 'A', 'B']),
            csvRow(['free_text', 'Valid two', '1']),
            csvRow(['single_choice', 'Two correct', '1', '1|2', 'A', 'B']),
            csvRow(['true_false', 'Valid three', '1', 'true']),
        ];

        $id = uploadImport($this->teacher, csvFile($lines));

        $this->actingAs($this->teacher)->get(importUrl($id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.valid_count', 3)
                ->where('report.invalid_count', 1)
                ->where('report.rows.2.line', 4)
                ->where('report.rows.2.valid', false)
                ->where('report.rows.2.errors', [__('assessment.validation.exactly_one_correct')])
                ->where('report.rows.0.errors', []));

        $this->actingAs($this->teacher)->post(importUrl("{$id}/confirm"))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', __('assessment.import.flash.imported', ['imported' => 3, 'skipped' => 1]));

        expect(Question::query()->orderBy('created_at')->orderBy('id')->pluck('prompt')->sort()->values()->all())
            ->toBe(['Valid one', 'Valid three', 'Valid two'])
            ->and(Question::query()->where('prompt', 'Two correct')->exists())->toBeFalse();
    });

    it('reports every DATA-017 and DATA-016 row error', function (array $cells, string $error) {
        $id = uploadImport($this->teacher, csvFile([csvHeader(), csvRow($cells)]));

        $this->actingAs($this->teacher)->get(importUrl($id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.rows.0.valid', false)
                ->where('report.rows.0.errors', fn ($errors) => in_array($error, collect($errors)->all(), true)));
    })->with([
        'unknown type' => [['essay', 'Q', '1'], 'The selected question type is invalid.'],
        'points zero' => [['free_text', 'Q', '0'], 'Points must be more than 0 and at most 100.'],
        'points over 100' => [['free_text', 'Q', '100.01'], 'Points must be more than 0 and at most 100.'],
        'three decimals' => [['free_text', 'Q', '1.255'], 'Points may have at most 2 decimal places.'],
        'empty prompt' => [['free_text', '', '1'], 'The question field is required.'],
        'one option' => [['single_choice', 'Q', '1', '1', 'A'], 'A choice question needs from 2 to 10 options.'],
        'no correct option' => [['multiple_choice', 'Q', '1', '', 'A', 'B'], 'Mark at least one option as correct.'],
        'correct out of range' => [['single_choice', 'Q', '1', '3', 'A', 'B'], 'The correct column must list numbers of the given options, separated by |.'],
        'correct not a number' => [['single_choice', 'Q', '1', 'A', 'A', 'B'], 'The correct column must list numbers of the given options, separated by |.'],
        'empty option in between' => [['single_choice', 'Q', '1', '1', 'A', '', 'C'], 'The option text field is required.'],
        'true/false with options' => [['true_false', 'Q', '1', 'true', 'yes', 'no'], 'A true/false row has no options; write true or false in the correct column.'],
        'true/false bad answer' => [['true_false', 'Q', '1', 'yes'], 'The correct column of a true/false row must be true or false.'],
        'matching without separator' => [['matching', 'Q', '1', '', 'A => 1', 'B - 2'], 'Option 2 must be a pair written as "left => right".'],
        'matching empty side' => [['matching', 'Q', '1', '', 'A => 1', '=> 2'], 'The left item field is required.'],
        'matching one pair' => [['matching', 'Q', '1', '', 'A => 1'], 'A matching question needs from 2 to 10 pairs.'],
        'matching with correct' => [['matching', 'Q', '1', '1', 'A => 1', 'B => 2'], 'The correct column must be empty for this question type.'],
        'free text with options' => [['free_text', 'Q', '1', '', 'A'], 'This question type has no options.'],
        'free text with correct' => [['free_text', 'Q', '1', '1'], 'The correct column must be empty for this question type.'],
    ]);

    it('reports a row with more cells than columns', function () {
        $id = uploadImport($this->teacher, csvFile([csvHeader(), csvRow(['free_text', 'Q', '1']).',extra']));

        $this->actingAs($this->teacher)->get(importUrl($id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.rows.0.errors', [__('assessment.import.errors.extra_cells')]));
    });

    it('disables confirm when every row is invalid, and a confirm request imports nothing', function () {
        $id = uploadImport($this->teacher, csvFile([
            csvHeader(),
            csvRow(['free_text', 'Q', '0']),
            csvRow(['essay', 'Q', '1']),
        ]));

        $this->actingAs($this->teacher)->get(importUrl($id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.valid_count', 0)
                ->where('report.invalid_count', 2));

        $this->actingAs($this->teacher)->from(importUrl($id))->post(importUrl("{$id}/confirm"))
            ->assertRedirect(importUrl($id))
            ->assertSessionHasErrors(['import' => __('assessment.import.errors.no_valid_rows')]);

        expect(Question::query()->count())->toBe(0);
        Storage::disk('local')->assertExists(importPath($this->teacher, $id));
    });

    it('re-validates the stored file at confirm and skips a row that turned invalid', function () {
        $id = uploadImport($this->teacher, csvFile([
            csvHeader(),
            csvRow(['free_text', 'Stays valid', '1']),
            csvRow(['free_text', 'Turns invalid', '1']),
        ]));

        $this->actingAs($this->teacher)->get(importUrl($id))
            ->assertInertia(fn (Assert $page) => $page->where('report.valid_count', 2));

        Storage::disk('local')->put(importPath($this->teacher, $id), implode("\n", [
            csvHeader(),
            csvRow(['free_text', 'Stays valid', '1']),
            csvRow(['free_text', 'Turns invalid', '150']),
        ]));

        $this->actingAs($this->teacher)->post(importUrl("{$id}/confirm"))
            ->assertSessionHas('inertia.flash_data.toast.message', __('assessment.import.flash.imported', ['imported' => 1, 'skipped' => 1]));

        expect(Question::query()->pluck('prompt')->all())->toBe(['Stays valid']);
    });
});

describe('UC-030: files refused as a whole', function () {
    it('refuses a Windows-1253 file with the save-as-CSV-UTF-8 message', function () {
        $greek = (string) iconv('UTF-8', 'WINDOWS-1253', 'Πρωτεύουσα;');
        expect(mb_check_encoding($greek, 'UTF-8'))->toBeFalse();

        $file = csvFile([csvHeader(), csvRow(['free_text', $greek, '1'])]);

        $this->actingAs($this->teacher)->post(importUrl(), ['file' => $file])
            ->assertSessionHasErrors(['file' => __('assessment.import.refused.not_utf8')]);

        expect(Storage::disk('local')->allFiles('question-imports'))->toBe([]);
    });

    it('refuses a file with a missing or an unknown header', function (string $header, string $message) {
        $file = csvFile([$header, 'free_text,Q,1']);

        $this->actingAs($this->teacher)->post(importUrl(), ['file' => $file])
            ->assertSessionHasErrors(['file' => $message]);

        expect(Storage::disk('local')->allFiles('question-imports'))->toBe([]);
    })->with([
        'missing option_10' => [
            implode(',', array_slice(QuestionCsv::headers(), 0, 13)),
            'The first row is missing these columns: option_10.',
        ],
        'missing type' => [
            implode(',', array_slice(QuestionCsv::headers(), 1)),
            'The first row is missing these columns: type.',
        ],
        'unknown column' => [
            implode(',', [...QuestionCsv::headers(), 'difficulty']),
            'The first row has unknown or repeated columns: difficulty.',
        ],
        'repeated column' => [
            implode(',', [...QuestionCsv::headers(), 'prompt']),
            'The first row has unknown or repeated columns: prompt.',
        ],
    ]);

    it('refuses a file with 501 data rows and accepts one with 500', function () {
        $rows = array_fill(0, 501, csvRow(['free_text', 'Q', '1']));

        $this->actingAs($this->teacher)->post(importUrl(), ['file' => csvFile([csvHeader(), ...$rows])])
            ->assertSessionHasErrors(['file' => __('assessment.import.refused.too_many_rows', ['max' => 500])]);

        expect(Storage::disk('local')->allFiles('question-imports'))->toBe([]);

        uploadImport($this->teacher, csvFile([csvHeader(), ...array_slice($rows, 0, 500)]));
    });

    it('refuses a file over 1 MB', function () {
        $file = UploadedFile::fake()->createWithContent('big.csv', csvHeader()."\n".str_repeat("free_text,Q,1,,,,,,,,,,,\n", 50000));
        expect($file->getSize())->toBeGreaterThan(1024 * 1024);

        $this->actingAs($this->teacher)->post(importUrl(), ['file' => $file])
            ->assertSessionHasErrors(['file' => __('assessment.import.refused.too_large')]);

        expect(Storage::disk('local')->allFiles('question-imports'))->toBe([]);
    });

    it('refuses an empty file and a missing file', function () {
        $this->actingAs($this->teacher)->post(importUrl(), ['file' => UploadedFile::fake()->createWithContent('e.csv', "\n")])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->teacher)->post(importUrl(), [])
            ->assertSessionHasErrors(['file' => __('assessment.import.errors.file_required')]);
    });
});

describe('UC-030 / DATA-017: file lifecycle', function () {
    it('deletes the file when the import is confirmed', function () {
        $id = uploadImport($this->teacher, csvFile([csvHeader(), csvRow(['free_text', 'Q', '1'])]));

        $this->actingAs($this->teacher)->post(importUrl("{$id}/confirm"));

        Storage::disk('local')->assertMissing(importPath($this->teacher, $id));
        $this->actingAs($this->teacher)->get(importUrl($id))->assertNotFound();
    });

    it('imports nothing and deletes the file when the import is cancelled', function () {
        $id = uploadImport($this->teacher, csvFile([csvHeader(), csvRow(['free_text', 'Q', '1'])]));

        $this->actingAs($this->teacher)->delete(importUrl($id))
            ->assertRedirect(test()->tenantUrl($this->acme, 'question-bank'));

        Storage::disk('local')->assertMissing(importPath($this->teacher, $id));
        expect(Question::query()->count())->toBe(0);
        $this->actingAs($this->teacher)->post(importUrl("{$id}/confirm"))->assertNotFound();
        expect(Question::query()->count())->toBe(0);
    });

    it('deletes leftover files older than 24 hours with the daily command and keeps newer ones', function () {
        $disk = Storage::disk('local');
        $old = 'question-imports/org/user/'.Str::uuid().'.csv';
        $recent = 'question-imports/org/user/'.Str::uuid().'.csv';
        $disk->put($old, 'x');
        $disk->put($recent, 'x');
        touch($disk->path($old), now()->subHours(25)->getTimestamp());
        touch($disk->path($recent), now()->subHours(23)->getTimestamp());

        $this->artisan('assessment:prune-question-imports')->assertSuccessful();

        $disk->assertMissing($old);
        $disk->assertExists($recent);
    });

    it('schedules the prune command daily', function () {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'assessment:prune-question-imports'));

        expect($events)->toHaveCount(1)
            ->and($events->first()->expression)->toBe('0 0 * * *');
    });
});

describe('UC-030: the template', function () {
    it('has the header and one valid example row per type', function () {
        $response = $this->actingAs($this->teacher)->get(importUrl('template'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('question-import-template.csv');

        $contents = (string) $response->getContent();
        $lines = preg_split('/\r\n/', trim(substr($contents, 3)));

        expect(str_starts_with($contents, "\xEF\xBB\xBF"))->toBeTrue()
            ->and($lines[0])->toBe(csvHeader())
            ->and($lines)->toHaveCount(6);

        $id = uploadImport($this->teacher, UploadedFile::fake()->createWithContent('template.csv', $contents));

        $this->actingAs($this->teacher)->get(importUrl($id))
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.valid_count', 5)
                ->where('report.rows', fn ($rows) => collect($rows)->pluck('type')->all() === array_map(fn (QuestionType $t) => $t->value, QuestionType::cases())));
    });

    it('follows the UI language', function () {
        $this->teacher->forceFill(['locale' => 'el'])->save();

        $contents = (string) $this->actingAs($this->teacher)->get(importUrl('template'))->getContent();

        expect($contents)->toContain('Ποια είναι η πρωτεύουσα της Ελλάδας;')
            ->and($contents)->toContain(csvHeader());
    });

    it('shows the upload page', function () {
        $this->actingAs($this->teacher)->get(importUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Assessment/QuestionBank/Import'));
    });
});

describe('UC-030 / OWN-016: only the uploader confirms or cancels', function () {
    it('refuses another teacher of the organisation and changes nothing', function () {
        $id = uploadImport($this->teacher, csvFile(oneRowPerType()));
        $other = User::factory()->teacher()->create();
        $before = importSnapshot();

        $this->actingAs($other)->get(importUrl($id))->assertNotFound();
        $this->actingAs($other)->post(importUrl("{$id}/confirm"))->assertNotFound();
        $this->actingAs($other)->delete(importUrl($id))->assertNotFound();

        expect(importSnapshot())->toBe($before);
        Storage::disk('local')->assertExists(importPath($this->teacher, $id));
    });

    it('refuses a teacher of another organisation and changes nothing', function () {
        $id = uploadImport($this->teacher, csvFile(oneRowPerType()));
        $beta = $this->createOrganization(['subdomain' => 'beta']);
        $foreign = $this->asTenant($beta, fn () => User::factory()->teacher()->create());
        $before = importSnapshot();

        // On their own organisation the import does not exist.
        $this->actingAs($foreign)->post(importUrl("{$id}/confirm", $beta))->assertNotFound();
        $this->actingAs($foreign)->delete(importUrl($id, $beta))->assertNotFound();
        // On the uploader's organisation they cannot act at all.
        expect($this->actingAs($foreign)->post(importUrl("{$id}/confirm"))->status())->toBeIn([403, 404]);
        expect($this->actingAs($foreign)->delete(importUrl($id))->status())->toBeIn([403, 404]);

        expect(importSnapshot())->toBe($before);
        Storage::disk('local')->assertExists(importPath($this->teacher, $id));
    });

    it('refuses org admins and students every import route', function (string $role) {
        $id = uploadImport($this->teacher, csvFile(oneRowPerType()));
        $actor = match ($role) {
            'admin' => User::factory()->orgAdmin()->create(),
            'student' => User::factory()->student()->create(),
        };
        $before = importSnapshot();

        $this->actingAs($actor)->get(importUrl())->assertForbidden();
        $this->actingAs($actor)->get(importUrl('template'))->assertForbidden();
        $this->actingAs($actor)->post(importUrl(), ['file' => csvFile(oneRowPerType())])->assertForbidden();
        $this->actingAs($actor)->get(importUrl($id))->assertForbidden();
        $this->actingAs($actor)->post(importUrl("{$id}/confirm"))->assertForbidden();
        $this->actingAs($actor)->delete(importUrl($id))->assertForbidden();

        expect(importSnapshot())->toBe($before)
            ->and(Storage::disk('local')->allFiles('question-imports'))->toBe([importPath($this->teacher, $id)]);
    })->with(['admin', 'student']);
});
