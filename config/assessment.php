<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Question import disk
    |--------------------------------------------------------------------------
    |
    | Private Storage disk of uploaded question CSV files between the report
    | and the confirm or cancel of an import (DATA-017, SEC-010). Files live
    | under question-imports/<organization_id>/<user_id>/; an S3-compatible
    | disk can replace the local one (OPS-001).
    |
    */

    'question_import_disk' => env('ASSESSMENT_QUESTION_IMPORT_DISK', 'local'),

];
