<?php

/*
| Ρυθμίσεις οργανισμού του διαχειριστή (UC-033, DATA-011). Κάθε κλειδί
| υπάρχει και στο lang/en/organization.php.
*/

return [

    'nav' => 'Οργανισμός',

    'settings' => [
        'title' => 'Ρυθμίσεις οργανισμού',
        'description' => 'Λογότυπο, χρώμα έμφασης, ορολογία, βαθμολόγηση και γλώσσα του οργανισμού σας.',
        'subdomain' => 'Υποτομέας',
        'subdomain_help' => 'Ο υποτομέας δεν αλλάζει.',
        'logo' => 'λογότυπο',
        'logo_label' => 'Λογότυπο',
        'logo_help' => 'PNG, JPG ή SVG, έως 2 MB.',
        'logo_none' => 'Χωρίς λογότυπο: εμφανίζεται το λογότυπο της εφαρμογής.',
        'logo_alt' => 'Τρέχον λογότυπο',
        'remove_logo' => 'Αφαίρεση λογότυπου',
        'accent_color' => 'χρώμα έμφασης',
        'accent_color_label' => 'Χρώμα έμφασης',
        'accent_color_help' => 'Μορφή #RRGGBB. Αφήστε το κενό για το προεπιλεγμένο χρώμα.',
        'accent_color_clear' => 'Καθαρισμός',
        'terminology' => 'Ορολογία',
        'student_term_el' => 'όρος μαθητή (ελληνικά)',
        'student_term_en' => 'όρος μαθητή (αγγλικά)',
        'teacher_term_el' => 'όρος καθηγητή (ελληνικά)',
        'teacher_term_en' => 'όρος καθηγητή (αγγλικά)',
        'student_term_el_label' => 'Όρος μαθητή (ελληνικά)',
        'student_term_en_label' => 'Όρος μαθητή (αγγλικά)',
        'teacher_term_el_label' => 'Όρος καθηγητή (ελληνικά)',
        'teacher_term_en_label' => 'Όρος καθηγητή (αγγλικά)',
        'grading_scale' => 'κλίμακα βαθμολόγησης',
        'grading_scale_label' => 'Κλίμακα βαθμολόγησης',
        'leaderboard' => 'πίνακας κατάταξης',
        'leaderboard_label' => 'Εμφάνιση πινάκων κατάταξης',
        'default_locale' => 'προεπιλεγμένη γλώσσα',
        'default_locale_label' => 'Προεπιλεγμένη γλώσσα',
        'default_locale_help' => 'Για επισκέπτες και για χρήστες που δεν έχουν επιλέξει γλώσσα.',
        'save' => 'Αποθήκευση',
    ],

    'grading_scales' => [
        'scale_0_10' => '0 έως 10',
        'scale_0_100' => '0 έως 100',
        'points_pass_mark' => 'Βαθμοί με βάση επιτυχίας',
    ],

    'locales' => [
        'el' => 'Ελληνικά',
        'en' => 'Αγγλικά',
    ],

    'errors' => [
        'accent_color' => 'Το χρώμα έμφασης πρέπει να έχει τη μορφή #RRGGBB, π.χ. #1A2B3C.',
        'logo_type' => 'Το λογότυπο πρέπει να είναι αρχείο PNG, JPG ή SVG.',
        'logo_svg' => 'Αυτό το αρχείο SVG δεν μπορεί να διαβαστεί.',
    ],

    'flash' => [
        'saved' => 'Οι ρυθμίσεις αποθηκεύτηκαν.',
    ],

];
