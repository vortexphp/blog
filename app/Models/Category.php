<?php

declare(strict_types=1);

namespace App\Models;

use Vortex\Database\Model;
use Vortex\Support\StringHelp;

final class Category extends Model
{
    /** @var list<string> */
    protected static array $fillable = ['name', 'slug'];

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        /** @var list<self> */
        return static::query()
            ->orderBy('name', 'ASC')
            ->get();
    }

    public static function findBySlug(string $slug): ?self
    {
        /** @var self|null */
        return static::query()->where('slug', $slug)->first();
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
            $base = 'category';
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
     * @return list<self>
     */
    public static function allForManagement(): array
    {
        /** @var list<self> */
        return static::query()
            ->orderBy('name', 'ASC')
            ->get();
    }
}
