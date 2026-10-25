<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One row per real staff member. Approval independence is judged on this row, not on the account.
        Schema::create('staff_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('person_ref')->unique();
            $t->string('full_name');
            $t->string('work_email')->unique();
            $t->string('status')->default('invited')->index(); // invited | active | suspended | left
            $t->timestamp('identity_checked_at')->nullable();
            $t->foreignId('identity_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
        });

        Schema::create('staff_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('staff_profile_id')->constrained('staff_profiles')->restrictOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('staff_profile_id');
        });
        Schema::dropIfExists('staff_profiles');
    }
};
