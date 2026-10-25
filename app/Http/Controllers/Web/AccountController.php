<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        return view('account.dashboard', ['user' => $request->user()]);
    }

    public function security(Request $request)
    {
        $user = $request->user();

        return view('account.security', [
            'user' => $user,
            'mfaConfirmed' => $user->two_factor_confirmed_at !== null,
            'mfaStarted' => $user->two_factor_secret !== null,
        ]);
    }
}
