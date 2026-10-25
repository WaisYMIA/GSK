<?php

namespace App\Support\Access;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Revokes server-side sessions (database session driver) and rotates the remember token.
// Called on suspension, role or permission changes and password resets.
class SessionRevoker
{
    public function revokeAll(User $user): int
    {
        $deleted = 0;
        if (Schema::hasTable('sessions')) {
            $deleted = DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        return $deleted;
    }
}
