<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\Post;
use Vortex\Http\Csrf;
use Vortex\Http\Request;
use Vortex\Http\Response;
use Vortex\Http\Session;
use Vortex\Support\NumberHelp;
use Vortex\Support\StringHelp;
use Vortex\Validation\Validator;
use Vortex\View\View;

final class BlogManageController
{
    public function index(): Response
    {
        $uid = Session::authUserId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        $page = NumberHelp::parseInt(Request::query()['page'] ?? null, 1, 1, 500);
        $pagination = Post::forUserPaginated($uid, $page, 15)
            ->withBasePath(\route('blog.manage.index'));

        return View::html('blog.manage.index', [
            'title' => \trans('blog.manage.title'),
            'posts' => $pagination->items,
            'pagination' => $pagination,
            'postCategoryMap' => self::postCategoryMap($pagination->items),
        ]);
    }

    /**
     * @param list<Post> $posts
     *
     * @return array<int, Category>
     */
    private static function postCategoryMap(array $posts): array
    {
        $ids = [];
        foreach ($posts as $p) {
            $cid = $p->category_id ?? null;
            if ($cid !== null && (int) $cid > 0) {
                $ids[] = (int) $cid;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        /** @var list<Category> $rows */
        $rows = Category::query()->whereIn('id', $ids)->get();
        $map = [];
        foreach ($rows as $c) {
            $map[(int) $c->id] = $c;
        }

        return $map;
    }

    public function create(): Response
    {
        return View::html('blog.manage.form', [
            'title' => \trans('blog.manage.new_title'),
            'post' => null,
            'categories' => Category::ordered(),
            'errors' => [],
            'old' => [
                'title' => '',
                'slug' => '',
                'excerpt' => '',
                'body' => '',
                'published_at' => '',
                'category_id' => '',
            ],
        ]);
    }

    public function store(): Response
    {
        $uid = Session::authUserId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        if (! Csrf::validate()) {
            Session::flash('errors', ['_form' => \trans('auth.csrf_invalid')]);

            return Response::redirect('/blog/manage/posts/new', 302);
        }

        $data = $this->inputFromRequest();
        $validation = Validator::make(
            $data,
            [
                'title' => 'required',
                'body' => 'required',
            ],
            [
                'title.required' => \trans('blog.manage.validation.title'),
                'body.required' => \trans('blog.manage.validation.body'),
            ],
        );

        if ($validation->failed()) {
            Session::flash('errors', $validation->errors());
            Session::flash('old', $data);

            return Response::redirect('/blog/manage/posts/new', 302);
        }

        $categoryResolution = $this->categoryIdFromInput($data['category_id'] ?? '');
        if ($categoryResolution['error'] !== null) {
            Session::flash('errors', ['category_id' => $categoryResolution['error']]);
            Session::flash('old', $data);

            return Response::redirect('/blog/manage/posts/new', 302);
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Post::makeUniqueSlug((string) $data['title']);
        } else {
            $slug = StringHelp::slug($slug);
            if ($slug === '') {
                $slug = Post::makeUniqueSlug((string) $data['title']);
            } elseif (Post::slugTaken($slug)) {
                $slug = Post::makeUniqueSlug($slug);
            }
        }

        $publishedAt = $this->normalizePublishedAt($data['published_at'] ?? '');

        Post::create([
            'user_id' => $uid,
            'category_id' => $categoryResolution['id'],
            'title' => trim((string) $data['title']),
            'slug' => $slug,
            'excerpt' => trim((string) ($data['excerpt'] ?? '')) ?: null,
            'body' => (string) $data['body'],
            'published_at' => $publishedAt,
        ]);

        Session::flash('status', \trans('blog.manage.created'));

        return Response::redirect('/blog/manage', 302);
    }

    public function edit(string $id): Response
    {
        $uid = Session::authUserId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        $post = Post::find((int) $id);
        if ($post === null || (int) ($post->user_id ?? 0) !== $uid) {
            return View::html('errors.404', ['title' => \trans('errors.404.title')], 404);
        }

        $errors = Session::flash('errors');
        $oldFlash = Session::flash('old');
        $defaults = [
            'title' => (string) ($post->title ?? ''),
            'slug' => (string) ($post->slug ?? ''),
            'excerpt' => (string) ($post->excerpt ?? ''),
            'body' => (string) ($post->body ?? ''),
            'published_at' => $this->publishedAtForInput($post->published_at ?? null),
            'category_id' => isset($post->category_id) && $post->category_id !== null ? (string) (int) $post->category_id : '',
        ];
        $old = is_array($oldFlash) ? array_merge($defaults, $oldFlash) : $defaults;

        return View::html('blog.manage.form', [
            'title' => \trans('blog.manage.edit_title'),
            'post' => $post,
            'categories' => Category::ordered(),
            'errors' => is_array($errors) ? $errors : [],
            'old' => $old,
        ]);
    }

    public function update(string $id): Response
    {
        $uid = Session::authUserId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        $post = Post::find((int) $id);
        if ($post === null || (int) ($post->user_id ?? 0) !== $uid) {
            return View::html('errors.404', ['title' => \trans('errors.404.title')], 404);
        }

        if (! Csrf::validate()) {
            Session::flash('errors', ['_form' => \trans('auth.csrf_invalid')]);

            return Response::redirect('/blog/manage/posts/' . $id . '/edit', 302);
        }

        $data = $this->inputFromRequest();
        $validation = Validator::make(
            $data,
            [
                'title' => 'required',
                'body' => 'required',
            ],
            [
                'title.required' => \trans('blog.manage.validation.title'),
                'body.required' => \trans('blog.manage.validation.body'),
            ],
        );

        if ($validation->failed()) {
            Session::flash('errors', $validation->errors());
            Session::flash('old', $data);

            return Response::redirect('/blog/manage/posts/' . $id . '/edit', 302);
        }

        $categoryResolution = $this->categoryIdFromInput($data['category_id'] ?? '');
        if ($categoryResolution['error'] !== null) {
            Session::flash('errors', ['category_id' => $categoryResolution['error']]);
            Session::flash('old', $data);

            return Response::redirect('/blog/manage/posts/' . $id . '/edit', 302);
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Post::makeUniqueSlug((string) $data['title'], (int) $post->id);
        } else {
            $slug = StringHelp::slug($slug);
            if ($slug === '') {
                $slug = Post::makeUniqueSlug((string) $data['title'], (int) $post->id);
            } elseif (Post::slugTaken($slug, (int) $post->id)) {
                $slug = Post::makeUniqueSlug($slug, (int) $post->id);
            }
        }

        $publishedAt = $this->normalizePublishedAt($data['published_at'] ?? '');

        $post->update([
            'category_id' => $categoryResolution['id'],
            'title' => trim((string) $data['title']),
            'slug' => $slug,
            'excerpt' => trim((string) ($data['excerpt'] ?? '')) ?: null,
            'body' => (string) $data['body'],
            'published_at' => $publishedAt,
        ]);

        Session::flash('status', \trans('blog.manage.updated'));

        return Response::redirect('/blog/manage', 302);
    }

    public function destroy(string $id): Response
    {
        $uid = Session::authUserId();
        if ($uid === null) {
            return Response::redirect('/login', 302);
        }

        $post = Post::find((int) $id);
        if ($post === null || (int) ($post->user_id ?? 0) !== $uid) {
            return View::html('errors.404', ['title' => \trans('errors.404.title')], 404);
        }

        if (! Csrf::validate()) {
            Session::flash('errors', ['_form' => \trans('auth.csrf_invalid')]);

            return Response::redirect('/blog/manage', 302);
        }

        $post->delete();
        Session::flash('status', \trans('blog.manage.deleted'));

        return Response::redirect('/blog/manage', 302);
    }

    /**
     * @return array{error: ?string, id: ?int}
     */
    private function categoryIdFromInput(mixed $raw): array
    {
        if ($raw === '' || $raw === null) {
            return ['error' => null, 'id' => null];
        }
        if (is_string($raw) || is_int($raw)) {
            $id = (int) $raw;
        } else {
            return ['error' => null, 'id' => null];
        }
        if ($id <= 0) {
            return ['error' => null, 'id' => null];
        }
        if (Category::find($id) === null) {
            return ['error' => \trans('blog.manage.validation.category_invalid'), 'id' => null];
        }

        return ['error' => null, 'id' => $id];
    }

    /**
     * @return array<string, string>
     */
    private function inputFromRequest(): array
    {
        return [
            'title' => trim((string) Request::input('title', '')),
            'slug' => trim((string) Request::input('slug', '')),
            'excerpt' => trim((string) Request::input('excerpt', '')),
            'body' => (string) Request::input('body', ''),
            'published_at' => trim((string) Request::input('published_at', '')),
            'category_id' => trim((string) Request::input('category_id', '')),
        ];
    }

    private function normalizePublishedAt(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $raw);
        if ($dt === false) {
            return null;
        }

        return $dt->format('Y-m-d H:i:s');
    }

    private function publishedAtForInput(mixed $publishedAt): string
    {
        if (! is_string($publishedAt) || $publishedAt === '') {
            return '';
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $publishedAt);

        return $dt === false ? '' : $dt->format('Y-m-d\TH:i');
    }
}
