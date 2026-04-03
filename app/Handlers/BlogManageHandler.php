<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Models\Post;
use Vortex\Http\Csrf;
use Vortex\Http\Request;
use Vortex\Http\Response;
use Vortex\Http\Session;
use Vortex\Support\NumberHelp;
use Vortex\Support\StringHelp;
use Vortex\Validation\Validator;
use Vortex\View\View;

final class BlogManageHandler
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
        ]);
    }

    public function create(): Response
    {
        return View::html('blog.manage.form', [
            'title' => \trans('blog.manage.new_title'),
            'post' => null,
            'errors' => [],
            'old' => [
                'title' => '',
                'slug' => '',
                'excerpt' => '',
                'body' => '',
                'published_at' => '',
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
        ];
        $old = is_array($oldFlash) ? array_merge($defaults, $oldFlash) : $defaults;

        return View::html('blog.manage.form', [
            'title' => \trans('blog.manage.edit_title'),
            'post' => $post,
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
