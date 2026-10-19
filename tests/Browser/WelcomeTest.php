<?php

/*
| Browser smoke test: the Inertia/Vue frontend boots and renders UI strings
| from lang/en and lang/el.
*/

it('renders the welcome page in English', function () {
    config(['app.locale' => 'en']);

    visit('/')
        ->assertSee('Course spaces with interactive activities')
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});

it('renders the welcome page in Greek', function () {
    config(['app.locale' => 'el']);

    visit('/')
        ->assertSee('Χώροι μαθημάτων με διαδραστικές δραστηριότητες')
        ->assertSee('Σύνδεση')
        ->assertNoJavaScriptErrors();
});
