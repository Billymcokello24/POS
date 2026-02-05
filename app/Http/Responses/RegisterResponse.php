<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
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
        $home = $isSuper ? '/admin/dashboard' : config('fortify.home');

        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        // Show preloader screen before redirecting to dashboard
        return Inertia::render('LoadingScreen', [
            'redirectTo' => $home
        ]);
    }
}
