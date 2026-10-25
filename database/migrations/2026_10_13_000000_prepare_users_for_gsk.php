<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Works whether or not the framework skeleton already created `users`.
// If the table exists it only adds missing GSK columns; if not, it creates the base table first.
return new class extends Migration {
    private array $added = ['account_type', 'status', 'last_login_at', 'invited_at', 'suspended_at', 'deleted_at'];

    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('email')->unique();
                $t->timestamp('email_verified_at')->nullable();
                $t->string('password');
                $t->rememberToken();
                $t->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'account_type')) {
                $t->string('account_type')->default('candidate')->index();
            }
            if (! Schema::hasColumn('users', 'status')) {
                $t->string('status')->default('active')->index();
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $t->timestamp('last_login_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'invited_at')) {
                $t->timestamp('invited_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'suspended_at')) {
                $t->timestamp('suspended_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $t->softDeletes();
            }
        });
    }

    public function down(): void
    {
        $present = array_values(array_filter($this->added, fn (string $c) => Schema::hasColumn('users', $c)));
        if ($present !== []) {
            Schema::table('users', function (Blueprint $t) use ($present) {
                $t->dropColumn($present);
            });
        }
    }
};
