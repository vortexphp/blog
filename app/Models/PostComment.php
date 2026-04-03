<?php

declare(strict_types=1);

namespace App\Models;

use Vortex\Database\Model;

final class PostComment extends Model
{
    protected static bool $timestamps = false;

    /** @var list<string> */
    protected static array $fillable = ['post_id', 'author_name', 'body', 'created_at'];

    /**
     * @return list<self>
     */
    public static function forPost(int $postId): array
    {
        /** @var list<self> */
        return static::query()
            ->where('post_id', $postId)
            ->orderBy('created_at', 'ASC')
            ->get();
    }

    public static function createForPost(int $postId, string $authorName, string $body): static
    {
        return parent::create([
            'post_id' => $postId,
            'author_name' => $authorName,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
