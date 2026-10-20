<?php

namespace App\Modules\Identity\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * RFC 9457 problem details for the JSON endpoints of Identity.
 */
final class ProblemDetails
{
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
            ['Content-Type' => 'application/problem+json', ...$headers],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
