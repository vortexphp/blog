<?php

declare(strict_types=1);

use Vortex\Database\Schema\Migration;
use Vortex\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('password');
            $table->text('avatar')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->index('idx_posts_user_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->text('body');
            $table->timestamp('published_at')->nullable()->index('idx_posts_published_at');
            $table->timestamps();
        });

        Schema::create('post_comments', function ($table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete()->index('idx_post_comments_post_id');
            $table->string('author_name');
            $table->text('body');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('users');
    }
};
