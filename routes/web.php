<?php

use Illuminate\Support\Facades\Route;

/*
| Application-wide routes only. Every module registers its own routes in its
| service provider (app/Modules/<Name>/routes.php).
*/

Route::inertia('/', 'Welcome')->name('home');
