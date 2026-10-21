<?php

namespace App\Modules\Activities\Http\Responses;

use App\Modules\Activities\Exceptions\GradingLocked;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * RFC 9457 problem details for the JSON endpoints of the wizard (autosave).
 */
final class ProblemDetails
{
    public const string TYPE_VALIDATION = 'urn:uplift:problem:validation';

    public const string TYPE_GRADING_LOCKED = 'urn:uplift:problem:grading-locked';

    public const string TYPE_HTTP = 'about:blank';

    /**
     * The problem response for an exception, or null to leave it to the
     * default handler (unexpected errors are reported and rendered as 500).
     */
    public static function for(Throwable $e): ?JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => self::response(
                422,
                self::TYPE_VALIDATION,
                (string) __('activities.problems.validation'),
                $e->getMessage(),
                ['errors' => $e->errors()],
            ),
            $e instanceof GradingLocked => self::response(
                409,
                self::TYPE_GRADING_LOCKED,
                (string) __('activities.problems.grading_locked'),
                (string) __('activities.errors.grading_locked'),
            ),
            $e instanceof HttpExceptionInterface => self::response(
                $e->getStatusCode(),
                self::TYPE_HTTP,
                JsonResponse::$statusTexts[$e->getStatusCode()] ?? 'Error',
                JsonResponse::$statusTexts[$e->getStatusCode()] ?? 'Error',
                [],
                $e->getHeaders(),
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $extensions
     * @param  array<string, string>  $headers
     */
    public static function response(int $status, string $type, string $title, string $detail, array $extensions = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(
            [
                'type' => $type,
                'title' => $title,
                'status' => $status,
                'detail' => $detail,
                ...$extensions,
            ],
            $status,
            [...$headers, 'Content-Type' => 'application/problem+json'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
