<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Http\Middleware\EnforceSessionLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * C-8: called by open host pages about every 10 minutes. The request itself keeps the idle
 * session alive; the answer says when the absolute limit ends it.
 */
final class SessionStatusController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['ends_at' => EnforceSessionLimit::endsAt($request)])
            ->header('Cache-Control', 'no-store, private');
    }
}
