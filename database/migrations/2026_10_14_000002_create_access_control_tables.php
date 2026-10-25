<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('is_system')->default(false);
            $t->boolean('is_privileged')->default(false);
            $t->boolean('requires_staff_profile')->default(true);
            $t->timestamps();
        });

        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('module')->index();
            $t->string('description');
            $t->string('status')->default('active')->index(); // active | deferred
            $t->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $t->unique(['role_id', 'permission_id']);
        });

        Schema::create('role_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $t->string('scope_type', 32)->default('global'); // global | organisation | programme | partner | case
            $t->unsignedBigInteger('scope_id')->nullable();
            $t->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reason')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['user_id', 'revoked_at']);
            $t->index(['scope_type', 'scope_id']);
        });

        Schema::create('permission_overrides', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('permission_id')->constrained('permissions')->restrictOnDelete();
            $t->string('effect', 8); // allow | deny
            $t->string('scope_type', 32)->default('global');
            $t->unsignedBigInteger('scope_id')->nullable();
            $t->text('reason');
            $t->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        foreach (['permission_overrides', 'role_assignments', 'role_permissions', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
