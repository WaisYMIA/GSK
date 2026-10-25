<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Task 1 change: the `users` table and the four `staff_*` tables are no longer created here.
// `users` is prepared by 2026_10_13_000000_prepare_users_for_gsk.php (alter or fallback create),
// and roles, permissions and assignments live in the 2026_10_14_* access-control migrations.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $t->string('legal_name');
            $t->string('registration_number')->nullable()->index();
            $t->string('country_code', 2)->default('KE');
            $t->string('verification_status')->default('pending')->index();
            $t->text('verification_notes')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
