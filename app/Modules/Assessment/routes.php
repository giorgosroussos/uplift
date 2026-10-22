<?php

use App\Modules\Assessment\Http\Controllers\GradingController;
use App\Modules\Assessment\Http\Controllers\QuestionBankController;
use App\Modules\Assessment\Http\Controllers\QuestionImportController;
use App\Modules\Assessment\Http\Controllers\QuizAttemptController;
use App\Modules\Assessment\Http\Controllers\ResultController;
use Illuminate\Support\Facades\Route;

/*
| Assessment routes (web middleware group), on organisation subdomains only.
| {question}, {attempt} and {answer} bind only records of the current
| tenant; QuestionPolicy applies OWN-016, QuizAttemptPolicy OWN-006 and
| AnswerPolicy OWN-007.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    // UC-023: the teacher's personal question bank.
    Route::get('question-bank', [QuestionBankController::class, 'index'])->name('assessment.questions.index');
    // UC-022: JSON for copying bank questions into a quiz (RFC 9457 errors).
    Route::get('question-bank/copyable', [QuestionBankController::class, 'copyable'])->name('assessment.questions.copyable');
    // UC-030: bulk import from CSV (DATA-017); {import} is found only among
    // the current user's own uploads.
    Route::get('question-bank/import', [QuestionImportController::class, 'create'])->name('assessment.imports.create');
    Route::get('question-bank/import/template', [QuestionImportController::class, 'template'])->name('assessment.imports.template');
    Route::post('question-bank/import', [QuestionImportController::class, 'store'])->name('assessment.imports.store');
    Route::get('question-bank/import/{import}', [QuestionImportController::class, 'show'])->whereUuid('import')->name('assessment.imports.show');
    Route::post('question-bank/import/{import}/confirm', [QuestionImportController::class, 'confirm'])->whereUuid('import')->name('assessment.imports.confirm');
    Route::delete('question-bank/import/{import}', [QuestionImportController::class, 'destroy'])->whereUuid('import')->name('assessment.imports.destroy');
    Route::get('question-bank/create', [QuestionBankController::class, 'create'])->name('assessment.questions.create');
    Route::post('question-bank', [QuestionBankController::class, 'store'])->name('assessment.questions.store');
    Route::get('question-bank/{question}/edit', [QuestionBankController::class, 'edit'])->name('assessment.questions.edit');
    Route::put('question-bank/{question}', [QuestionBankController::class, 'update'])->name('assessment.questions.update');
    Route::delete('question-bank/{question}', [QuestionBankController::class, 'destroy'])->name('assessment.questions.destroy');

    // UC-012: taking a self-paced quiz; {activity} is the quiz's activity.
    Route::get('quizzes/{activity}', [QuizAttemptController::class, 'quiz'])->name('assessment.quizzes.show');
    Route::post('quizzes/{activity}/attempts', [QuizAttemptController::class, 'start'])->name('assessment.attempts.store');
    Route::get('quiz-attempts/{attempt}', [QuizAttemptController::class, 'show'])->name('assessment.attempts.show');
    // JSON autosave of one answer (RFC 9457 errors).
    Route::put('quiz-attempts/{attempt}/answers/{quizQuestion}', [QuizAttemptController::class, 'answer'])->name('assessment.attempts.answers.update');
    Route::post('quiz-attempts/{attempt}/submit', [QuizAttemptController::class, 'submit'])->name('assessment.attempts.submit');

    // UC-017: a student's own results.
    Route::get('results', [ResultController::class, 'index'])->name('assessment.results.index');
    Route::get('results/{attempt}', [ResultController::class, 'show'])->name('assessment.results.show');

    // UC-026: the owning teacher grades a submitted attempt's answers.
    Route::get('grading/attempts/{attempt}', [GradingController::class, 'show'])->name('assessment.grading.show');
    Route::put('grading/answers/{answer}', [GradingController::class, 'update'])->name('assessment.grading.update');
});
