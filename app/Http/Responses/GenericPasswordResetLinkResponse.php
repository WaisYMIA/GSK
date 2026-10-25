<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

// One identical answer whether or not the address is registered, so the form cannot be used to find accounts.
class GenericPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const MESSAGE = 'If that email address is registered, a reset link has been sent.';

    public function __construct(protected string $status = '')
    {
    }

    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => self::MESSAGE], 200);
        }

        return back()->with('status', self::MESSAGE);
    }
}
