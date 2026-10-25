<?php

namespace App\Actions\Gsk;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ])->validate();

        // The model casts `password` as hashed, so the plain value is hashed on assignment.
        $user->forceFill(['password' => $input['password']])->save();
    }
}
