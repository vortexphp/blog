<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Throwable;
use Vortex\Console\Command;
use Vortex\Console\Input;
use Vortex\Crypto\Password;
use Vortex\Database\Connection;

final class DbSeedCommand extends Command
{
    public function description(): string
    {
        return 'Seed the database with demo users, posts, and comments. Pass --fresh to delete existing rows in blog tables first.';
    }

    protected function shouldBootApplication(): bool
    {
        return true;
    }

    protected function execute(Input $input): int
    {
        $conn = $this->app()->container()->make(Connection::class);

        try {
            $conn->transaction(function () use ($input): void {
                if ($input->flag('fresh')) {
                    PostComment::query()->delete();
                    Post::query()->delete();
                    Category::query()->delete();
                    User::query()->delete();
                }

                $user = User::findByEmail('demo@example.com');
                if ($user === null) {
                    $user = User::create([
                        'name' => 'Demo Author',
                        'email' => 'demo@example.com',
                        'password' => Password::hash('password'),
                    ]);
                }

                $userId = (int) $user->id;
                $now = date('Y-m-d H:i:s');

                $general = Category::findBySlug('general');
                if ($general === null) {
                    $general = Category::create([
                        'name' => 'General',
                        'slug' => 'general',
                    ]);
                }
                $categoryId = (int) $general->id;

                $this->seedPostIfMissing($userId, [
                    'title' => 'Welcome to the blog',
                    'slug' => 'welcome-to-the-blog',
                    'excerpt' => 'A sample post created by the database seeder.',
                    'body' => "## Welcome\n\nThis is **demo content** from `php vortex db:seed`.\n\n- One\n- Two\n",
                    'published_at' => $now,
                    'category_id' => $categoryId,
                ]);

                $post = Post::findPublishedBySlug('welcome-to-the-blog');
                if ($post !== null
                    && ! PostComment::query()->where('post_id', (int) $post->id)->exists()) {
                    PostComment::createForPost(
                        (int) $post->id,
                        'Visitor',
                        'Great to see the blog up and running!',
                    );
                }

                $this->seedPostIfMissing($userId, [
                    'title' => 'Draft ideas (unpublished)',
                    'slug' => 'draft-ideas-unpublished',
                    'excerpt' => null,
                    'body' => "This post has no `published_at` and should not appear on the public index.",
                    'published_at' => null,
                    'category_id' => null,
                ]);
            });
        } catch (Throwable $e) {
            $this->error('Seed failed: ' . $e->getMessage());

            return 1;
        }

        $this->info('Database seeded.');

        return 0;
    }

    /**
     * @param array{title: string, slug: string, excerpt: ?string, body: string, published_at: ?string, category_id: ?int} $data
     */
    private function seedPostIfMissing(int $userId, array $data): void
    {
        if (Post::query()->where('slug', $data['slug'])->exists()) {
            return;
        }

        Post::create([
            'user_id' => $userId,
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'],
            'body' => $data['body'],
            'published_at' => $data['published_at'],
        ]);
    }
}
