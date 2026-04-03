<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Vortex\Http\Csrf;
use Vortex\Http\Request;
use Vortex\Http\Response;
use Vortex\Http\Session;
use Vortex\Support\HtmlHelp;
use Vortex\Support\StringHelp;
use Vortex\Validation\Validator;
use Vortex\View\View;

final class BlogHandler
{
    public function index(): Response
    {
        $posts = Post::publishedRecent(50);

        return View::html('blog.index', [
            'title' => \trans('blog.title'),
            'posts' => $posts,
        ]);
    }

    public function show(string $slug): Response
    {
        $post = Post::findPublishedBySlug($slug);
        if ($post === null) {
            return View::html('errors.404', [
                'title' => \trans('errors.404.title'),
            ], 404);
        }

        $excerpt = is_string($post->excerpt ?? null) && ($post->excerpt ?? '') !== ''
            ? (string) $post->excerpt
            : HtmlHelp::excerpt((string) ($post->body ?? ''), 200);

        $comments = PostComment::forPost((int) $post->id);
        $errors = Session::flash('comment_errors');
        $oldFlash = Session::flash('comment_old');
        $defaultName = '';
        $uid = Session::authUserId();
        if ($uid !== null) {
            $u = User::find($uid);
            if ($u !== null && is_string($u->name ?? null)) {
                $defaultName = (string) $u->name;
            }
        }
        $commentOld = is_array($oldFlash)
            ? array_merge(['author_name' => $defaultName, 'body' => ''], $oldFlash)
            : ['author_name' => $defaultName, 'body' => ''];

        return View::html('blog.show', [
            'title' => (string) ($post->title ?? \trans('blog.title')),
            'post' => $post,
            'metaDescription' => $excerpt,
            'comments' => $comments,
            'commentErrors' => is_array($errors) ? $errors : [],
            'commentOld' => $commentOld,
        ]);
    }

    public function storeComment(string $slug): Response
    {
        $post = Post::findPublishedBySlug($slug);
        if ($post === null) {
            return View::html('errors.404', [
                'title' => \trans('errors.404.title'),
            ], 404);
        }

        if (! Csrf::validate()) {
            Session::flash('comment_errors', ['_form' => \trans('auth.csrf_invalid')]);

            return Response::redirect('/blog/' . rawurlencode($slug), 302);
        }

        $authorName = StringHelp::squish(trim((string) Request::input('author_name', '')));
        $body = (string) Request::input('body', '');

        $validation = Validator::make(
            [
                'author_name' => $authorName,
                'body' => $body,
            ],
            [
                'author_name' => 'required|string|max:80',
                'body' => 'required|string|max:4000',
            ],
            [
                'author_name.required' => \trans('blog.comments.validation.name'),
                'author_name.max' => \trans('blog.comments.validation.name_max'),
                'body.required' => \trans('blog.comments.validation.body'),
                'body.max' => \trans('blog.comments.validation.body_max'),
            ],
        );

        if ($validation->failed()) {
            Session::flash('comment_errors', $validation->errors());
            Session::flash('comment_old', [
                'author_name' => $authorName,
                'body' => $body,
            ]);

            return Response::redirect('/blog/' . rawurlencode($slug), 302);
        }

        PostComment::createForPost((int) $post->id, $authorName, $body);
        Session::flash('comment_status', \trans('blog.comments.posted'));

        return Response::redirect('/blog/' . rawurlencode($slug), 302);
    }
}
