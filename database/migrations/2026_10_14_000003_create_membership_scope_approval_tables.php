<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organisation_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('membership_role')->default('member'); // owner | admin | member
            $t->string('status')->default('pending')->index(); // pending | active | suspended | removed
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->unique(['organisation_id', 'user_id']);
        });

        Schema::create('partner_programme_scopes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained('organisations')->restrictOnDelete();
            $t->foreignId('programme_id')->constrained('programmes')->restrictOnDelete();
            $t->string('function', 64);
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['organisation_id', 'programme_id']);
        });

        Schema::create('approval_requests', function (Blueprint $t) {
            $t->id();
            $t->string('action_type')->index();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('requester_staff_profile_id')->nullable()->constrained('staff_profiles')->restrictOnDelete();
            $t->string('status')->default('pending')->index(); // pending | approved | rejected | blocked | expired | cancelled
            $t->text('reason');
            $t->json('payload')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('approval_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $t->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('decider_staff_profile_id')->constrained('staff_profiles')->restrictOnDelete();
            $t->string('decision', 8); // approve | reject
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->unique(['approval_request_id', 'decider_staff_profile_id']);
        });

        Schema::create('break_glass_grants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('staff_profile_id')->constrained('staff_profiles')->restrictOnDelete();
            $t->text('reason');
            $t->json('permission_keys');
            $t->timestamp('activated_at');
            $t->timestamp('expires_at');
            $t->timestamp('ended_at')->nullable();
            $t->string('review_status')->default('pending')->index(); // pending | reviewed
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewer_staff_profile_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('review_notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['break_glass_grants', 'approval_decisions', 'approval_requests', 'partner_programme_scopes', 'organisation_memberships'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
