<?php

namespace App\Modules\Activities\Enums;

/**
 * Steps of the activity wizard (UC-022). Basics belong to Activities;
 * settings and content to the activity's type; review publishes the
 * activity or keeps it as a draft.
 */
enum WizardStep: string
{
    case Basics = 'basics';
    case Settings = 'settings';
    case Content = 'content';
    case Review = 'review';

    /**
     * The steps whose data is autosaved.
     *
     * @return list<string>
     */
    public static function savedValues(): array
    {
        return [self::Basics->value, self::Settings->value, self::Content->value];
    }
}
