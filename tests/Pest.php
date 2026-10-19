<?php

use Tests\TestCase;

/*
| Feature and browser tests boot the Laravel application; unit and
| architecture tests stay framework-free unless they opt in.
*/

pest()->extend(TestCase::class)->in('Feature', 'Browser', 'Architecture');
