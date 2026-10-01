<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * K4: framework errors on JSON requests answer {code, message} like the domain errors, so
 * the pages show one kind of message. Domain exceptions render themselves before this runs.
 */
final class ApiError
{
    private const array CODES = [
        401 => 'UNAUTHENTICATED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        419 => 'SESSION_EXPIRED',
        429 => 'TOO_MANY_REQUESTS',
        503 => 'SERVICE_UNAVAILABLE',
    ];

    /**
     * The JSON answer, or null to keep Laravel's own (the debug page for server errors in
     * local development).
     */
    public static function render(Throwable $exception, bool $debug): ?JsonResponse
    {
        return match (true) {
            $exception instanceof ValidationException => self::json('VALIDATION_FAILED', 422, ['errors' => $exception->errors()]),
            $exception instanceof AuthenticationException => self::json('UNAUTHENTICATED', 401),
            $exception instanceof HttpExceptionInterface => self::json(
                self::CODES[$exception->getStatusCode()] ?? ($exception->getStatusCode() >= 500 ? 'SERVER_ERROR' : 'HTTP_ERROR'),
                $exception->getStatusCode(),
                headers: $exception->getHeaders(),
            ),
            $debug => null,
            default => self::json('SERVER_ERROR', 500),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    private static function json(string $code, int $status, array $extra = [], array $headers = []): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => __("errors.{$code}"), ...$extra], $status, $headers);
    }
}
