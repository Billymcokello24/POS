<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return Response
     */
    public function toResponse($request): Response
    {
        $isSuper = $request->user() && $request->user()->is_super_admin;
        \Log::debug('LoginResponse: User ' . ($request->user()?->email ?? 'Guest') . ' - Is Super: ' . ($isSuper ? 'YES' : 'NO'));

        $home = $isSuper ? '/admin/dashboard' : config('fortify.home');
        \Log::debug('LoginResponse: Redirecting to ' . $home);

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended($home);
    }
}
