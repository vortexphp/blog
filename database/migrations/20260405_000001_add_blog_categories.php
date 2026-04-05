<?php

declare(strict_types=1);

use Vortex\Database\DB;
use Vortex\Database\Schema\Migration;
use Vortex\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('posts', function ($table) {
            $table->foreignId('category_id')->nullable()->index('idx_posts_category_id');
        });
    }

    public function down(): void
    {
        $pdo = DB::connection()->pdo();
        $driver = strtolower((string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));

        if ($driver === 'sqlite') {
            $pdo->exec('ALTER TABLE posts DROP COLUMN category_id');
        } elseif ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE `posts` DROP COLUMN `category_id`');
        } elseif ($driver === 'pgsql') {
            $pdo->exec('ALTER TABLE posts DROP COLUMN IF EXISTS category_id');
        }

        Schema::dropIfExists('categories');
    }
};
