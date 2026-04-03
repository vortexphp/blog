<?php

declare(strict_types=1);

namespace App\Models;

use Vortex\Database\Model;
use Vortex\Database\QueryBuilder;
use Vortex\Pagination\Paginator;
use Vortex\Support\StringHelp;

final class Post extends Model
{
    /** @var list<string> */
    protected static array $fillable = ['user_id', 'title', 'slug', 'excerpt', 'body', 'published_at'];

    public static function published(): QueryBuilder
    {
        $now = date('Y-m-d H:i:s');

        return static::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now);
    }

    /**
     * @return list<self>
     */
    public static function publishedRecent(int $limit = 50): array
    {
        /** @var list<self> */
        return static::published()
            ->orderByDesc('published_at')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * @return list<self>
     */
    public static function forUser(int $userId): array
    {
        /** @var list<self> */
        return static::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get();
    }

    public static function forUserPaginated(int $userId, int $page, int $perPage = 15): Paginator
    {
        return static::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->paginate($page, $perPage);
    }

    public function user(): ?User
    {
        $uid = $this->user_id ?? null;
        if ($uid === null || (int) $uid === 0) {
            return null;
        }

        return User::find((int) $uid);
    }

    public static function findPublishedBySlug(string $slug): ?self
    {
        /** @var self|null */
        return static::published()->where('slug', $slug)->first();
    }

    public static function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        $q = static::query()->where('slug', $slug);
        if ($exceptId !== null) {
            $q->where('id', '!=', $exceptId);
        }

        return $q->exists();
    }

    public static function makeUniqueSlug(string $title, ?int $exceptId = null): string
    {
        $base = StringHelp::slug($title);
        if ($base === '') {
            $base = 'post';
        }

        $slug = $base;
        $n = 2;
        while (static::slugTaken($slug, $exceptId)) {
            $slug = $base . '-' . $n;
            ++$n;
        }

        return $slug;
    }

    /**
     * @return list<string>
     */
    protected static function excludedFromUpdate(): array
    {
        return ['user_id'];
    }

    public function isPublished(): bool
    {
        $p = $this->published_at ?? null;

        return is_string($p) && $p !== '' && $p <= date('Y-m-d H:i:s');
    }
}
