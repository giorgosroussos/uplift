<?php

namespace App\Modules\Assessment\Exceptions;

use RuntimeException;

/**
 * DATA-017: a question CSV refused as a whole (not UTF-8, wrong headers, too
 * many rows or too large). The message is translated and shown to the
 * teacher; it never contains file contents.
 */
class QuestionImportRefused extends RuntimeException {}
