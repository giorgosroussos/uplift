<?php

use App\Modules\Assessment\Http\Controllers\QuestionBankController;
use Illuminate\Support\Facades\Route;

/*
| Assessment routes (web middleware group), on organisation subdomains only.
| {question} binds only questions of the current tenant; QuestionPolicy
| applies OWN-016.
*/

Route::middleware(['tenant', 'auth', 'verified'])->group(function () {
    // UC-023: the teacher's personal question bank.
    Route::get('question-bank', [QuestionBankController::class, 'index'])->name('assessment.questions.index');
    Route::get('question-bank/create', [QuestionBankController::class, 'create'])->name('assessment.questions.create');
    Route::post('question-bank', [QuestionBankController::class, 'store'])->name('assessment.questions.store');
    Route::get('question-bank/{question}/edit', [QuestionBankController::class, 'edit'])->name('assessment.questions.edit');
    Route::put('question-bank/{question}', [QuestionBankController::class, 'update'])->name('assessment.questions.update');
    Route::delete('question-bank/{question}', [QuestionBankController::class, 'destroy'])->name('assessment.questions.destroy');
});
