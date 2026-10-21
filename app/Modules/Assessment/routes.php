<?php

use App\Modules\Assessment\Http\Controllers\QuestionBankController;
use App\Modules\Assessment\Http\Controllers\QuizAttemptController;
use Illuminate\Support\Facades\Route;

/*
| Assessment routes (web middleware group), on organisation subdomains only.
| {question} binds only questions of the current tenant; QuestionPolicy
| applies OWN-016.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    // UC-023: the teacher's personal question bank.
    Route::get('question-bank', [QuestionBankController::class, 'index'])->name('assessment.questions.index');
    // UC-022: JSON for copying bank questions into a quiz (RFC 9457 errors).
    Route::get('question-bank/copyable', [QuestionBankController::class, 'copyable'])->name('assessment.questions.copyable');
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
});
