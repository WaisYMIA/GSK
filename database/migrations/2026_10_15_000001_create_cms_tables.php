<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->string('kind')->default('page')->index(); // page | policy | programme
            $t->foreignId('programme_id')->nullable()->constrained('programmes')->nullOnDelete();
            $t->string('sensitive_area')->default('none'); // none | privacy | safeguarding | donation | legal | employment
            $t->string('status')->default('draft')->index(); // draft | in_review | published | archived
            $t->unsignedBigInteger('current_version_id')->nullable();
            $t->unsignedBigInteger('published_version_id')->nullable()->index();
            $t->timestamp('published_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->string('category')->nullable()->index();
            $t->string('sensitive_area')->default('none');
            $t->string('status')->default('draft')->index();
            $t->unsignedBigInteger('current_version_id')->nullable();
            $t->unsignedBigInteger('published_version_id')->nullable()->index();
            $t->timestamp('published_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Immutable history. Rollback publishes a copy as a new version.
        Schema::create('content_versions', function (Blueprint $t) {
            $t->id();
            $t->string('owner_type', 16); // page | post
            $t->unsignedBigInteger('owner_id');
            $t->unsignedInteger('version_number');
            $t->string('title');
            $t->text('summary')->nullable();
            $t->json('blocks');
            $t->string('seo_title')->nullable();
            $t->string('seo_description', 300)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('change_note')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['owner_type', 'owner_id', 'version_number']);
            $t->index(['owner_type', 'owner_id']);
        });

        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('stored_path');
            $t->string('mime', 32);
            $t->unsignedInteger('size_bytes');
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->string('alt_text', 300);
            $t->string('caption', 300)->nullable();
            $t->string('credit_or_source', 200);
            $t->boolean('rights_confirmed')->default(false);
            $t->boolean('people_consent_recorded')->default(false);
            $t->string('status')->default('approved')->index(); // approved | retired
            $t->timestamps();
        });

        // No IP address is stored.
        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email', 190);
            $t->string('topic', 120)->nullable();
            $t->text('message');
            $t->string('status')->default('new')->index(); // new | read | closed
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['contact_messages', 'media', 'content_versions', 'posts', 'pages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
