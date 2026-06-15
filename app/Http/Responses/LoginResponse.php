<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Http\RedirectResponse;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return new RedirectResponse('/dashboard');
    }
}
