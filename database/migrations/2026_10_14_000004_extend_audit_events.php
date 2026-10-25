<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $t) {
            $t->unsignedBigInteger('actor_staff_profile_id')->nullable()->after('actor_user_id');
            $t->string('outcome', 16)->default('success')->after('action')->index();
            $t->text('reason')->nullable()->after('outcome');
            $t->string('scope_type', 32)->nullable()->after('subject_id');
            $t->string('scope_id')->nullable()->after('scope_type');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $t) {
            $t->dropColumn(['actor_staff_profile_id', 'outcome', 'reason', 'scope_type', 'scope_id']);
        });
    }
};
