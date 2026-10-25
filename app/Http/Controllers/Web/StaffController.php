<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Access\AccessService;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function dashboard(Request $request, AccessService $access)
    {
        $user = $request->user();
        abort_unless($user->isStaff(), 403);

        return view('staff.dashboard', [
            'user' => $user,
            'canContent' => $access->can($user, 'content.pages.view') || $access->can($user, 'content.posts.view'),
            'canMedia' => $access->can($user, 'content.posts.manage_media'),
            'canContact' => $access->can($user, 'contact.messages.view'),
        ]);
    }
}
