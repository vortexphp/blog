<?php

declare(strict_types=1);

use Vortex\Database\Connection;
use Vortex\Database\Schema\Migration;
use Vortex\Database\Schema\Schema;

return new class implements Migration {
    public function id(): string
    {
        return '20260403_000001_create_blog_schema';
    }

    public function up(Connection $db): void
    {
        $schema = Schema::connection($db);

        $schema->create('users', static function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('password');
            $table->text('avatar')->nullable();
            $table->timestamps();
        });

        $schema->create('posts', static function ($table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->index('idx_posts_user_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->text('body');
            $table->timestamp('published_at')->nullable()->index('idx_posts_published_at');
            $table->timestamps();
        });

        $schema->create('post_comments', static function ($table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete()->index('idx_post_comments_post_id');
            $table->string('author_name');
            $table->text('body');
            $table->timestamp('created_at');
        });
    }

    public function down(Connection $db): void
    {
        $schema = Schema::connection($db);
        $schema->dropIfExists('post_comments');
        $schema->dropIfExists('posts');
        $schema->dropIfExists('users');
    }
};
