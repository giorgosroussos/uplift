<?php

/*
| Organisation settings of the org admin (UC-033, DATA-011): UI strings and
| server-side messages. Every key here must also exist in lang/el/organization.php.
*/

return [

    'nav' => 'Organisation',

    'settings' => [
        'title' => 'Organisation settings',
        'description' => 'Logo, accent colour, terminology, grading and language of your organisation.',
        'subdomain' => 'Subdomain',
        'subdomain_help' => 'The subdomain cannot be changed.',
        'logo' => 'logo',
        'logo_label' => 'Logo',
        'logo_help' => 'PNG, JPG or SVG, up to 2 MB.',
        'logo_none' => 'No logo: the app logo is shown.',
        'logo_alt' => 'Current logo',
        'remove_logo' => 'Remove logo',
        'accent_color' => 'accent colour',
        'accent_color_label' => 'Accent colour',
        'accent_color_help' => 'Format #RRGGBB. Leave empty for the default colour.',
        'accent_color_clear' => 'Clear',
        'terminology' => 'Terminology',
        'student_term_el' => 'student term (Greek)',
        'student_term_en' => 'student term (English)',
        'teacher_term_el' => 'teacher term (Greek)',
        'teacher_term_en' => 'teacher term (English)',
        'student_term_el_label' => 'Student term (Greek)',
        'student_term_en_label' => 'Student term (English)',
        'teacher_term_el_label' => 'Teacher term (Greek)',
        'teacher_term_en_label' => 'Teacher term (English)',
        'grading_scale' => 'grading scale',
        'grading_scale_label' => 'Grading scale',
        'leaderboard' => 'leaderboard',
        'leaderboard_label' => 'Show leaderboards',
        'default_locale' => 'default language',
        'default_locale_label' => 'Default language',
        'default_locale_help' => 'Used for visitors and for users who have not chosen a language.',
        'save' => 'Save',
    ],

    'grading_scales' => [
        'scale_0_10' => '0 to 10',
        'scale_0_100' => '0 to 100',
        'points_pass_mark' => 'Points with a pass mark',
    ],

    'locales' => [
        'el' => 'Greek',
        'en' => 'English',
    ],

    'errors' => [
        'accent_color' => 'The accent colour must have the format #RRGGBB, e.g. #1A2B3C.',
        'logo_type' => 'The logo must be a PNG, JPG or SVG file.',
        'logo_svg' => 'This SVG file cannot be read.',
    ],

    'flash' => [
        'saved' => 'Settings saved.',
    ],

];
