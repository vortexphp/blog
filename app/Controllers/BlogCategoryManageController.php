<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\Post;
use Vortex\Http\Csrf;
use Vortex\Http\Request;
use Vortex\Http\Response;
use Vortex\Http\Session;
use Vortex\Support\StringHelp;
use Vortex\Validation\Validator;
use Vortex\View\View;

final class BlogCategoryManageController
{
    public function index(): Response
    {
        if (Session::authUserId() === null) {
            return Response::redirect('/login', 302);
        }

        return View::html('blog.manage.categories.index', [
            'title' => \trans('blog.manage.categories.title'),
            'categories' => Category::allForManagement(),
        ]);
    }

    public function create(): Response
    {
        if (Session::authUserId() === null) {
            return Response::redirect('/login', 302);
        }

        $errors = Session::flash('errors');
        $oldFlash = Session::flash('old');

        return View::html('blog.manage.categories.form', [
            'title' => \trans('blog.manage.categories.new_title'),
            'category' => null,
            'errors' => is_array($errors) ? $errors : [],
            'old' => is_array($oldFlash) ? $oldFlash : ['name' => '', 'slug' => ''],
        ]);
    }

    public function store(): Response
    {
        if (Session::authUserId() === null) {
            return Response::redirect('/login', 302);
        }

        if (! Csrf::validate()) {
            Session::flash('errors', ['_form' => \trans('auth.csrf_invalid')]);

            return Response::redirect('/blog/manage/categories/new', 302);
        }

        $data = [
            'name' => trim((string) Request::input('name', '')),
            'slug' => trim((string) Request::input('slug', '')),
        ];

        $validation = Validator::make(
            $data,
            [
                'name' => 'required|string|max:120',
            ],
            [
                'name.required' => \trans('blog.manage.categories.validation.name'),
                'name.max' => \trans('blog.manage.categories.validation.name_max'),
            ],
        );

        if ($validation->failed()) {
            Session::flash('errors', $validation->errors());
            Session::flash('old', $data);

            return Response::redirect('/blog/manage/categories/new', 302);
        }

        $slug = $data['slug'];
        if ($slug === '') {
            $slug = Category::makeUniqueSlug($data['name']);
        } else {
            $slug = StringHelp::slug($slug);
            if ($slug === '') {
                $slug = Category::makeUniqueSlug($data['name']);
            } elseif (Category::slugTaken($slug)) {
                $slug = Category::makeUniqueSlug($slug);
            }
        }

        Category::create([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        Session::flash('status', \trans('blog.manage.categories.created'));

        return Response::redirect('/blog/manage/categories', 302);
    }

    public function edit(string $id): Response
    {
        if (Session::authUserId() === null) {
            return Response::redirect('/login', 302);
        }

        $category = Category::find((int) $id);
        if ($category === null) {
            return View::html('errors.404', ['title' => \trans('errors.404.title')], 404);
        }

        $errors = Session::flash('errors');
        $oldFlash = Session::flash('old');
        $defaults = [
            'name' => (string) ($category->name ?? ''),
            'slug' => (string) ($category->slug ?? ''),
        ];
        $old = is_array($oldFlash) ? array_merge($defaults, $oldFlash) : $defaults;

        return View::html('blog.manage.categories.form', [
            'title' => \trans('blog.manage.categories.edit_title'),
            'category' => $category,
            'errors' => is_array($errors) ? $errors : [],
            'old' => $old,
        ]);
    }

    public function update(string $id): Response
    {
        if (Session::authUserId() === null) {
            return Response::redirect('/login', 302);
        }

        $category = Category::find((int) $id);
        if ($category === null) {
            return View::html('errors.404', ['title' => \trans('errors.404.title')], 404);
        }

        if (! Csrf::validate()) {
            Session::flash('errors', ['_form' => \trans('auth.csrf_invalid')]);

            return Response::redirect('/blog/manage/categories/' . $id . '/edit', 302);
        }

        $data = [
            'name' => trim((string) Request::input('name', '')),
            'slug' => trim((string) Request::input('slug', '')),
        ];

        $validation = Validator::make(
            $data,
            [
                'name' => 'required|string|max:120',
            ],
            [
                'name.required' => \trans('blog.manage.categories.validation.name'),
                'name.max' => \trans('blog.manage.categories.validation.name_max'),
            ],
        );

        if ($validation->failed()) {
            Session::flash('errors', $validation->errors());
            Session::flash('old', $data);

            return Response::redirect('/blog/manage/categories/' . $id . '/edit', 302);
        }

        $slug = $data['slug'];
        if ($slug === '') {
            $slug = Category::makeUniqueSlug($data['name'], (int) $category->id);
        } else {
            $slug = StringHelp::slug($slug);
            if ($slug === '') {
                $slug = Category::makeUniqueSlug($data['name'], (int) $category->id);
            } elseif (Category::slugTaken($slug, (int) $category->id)) {
                $slug = Category::makeUniqueSlug($slug, (int) $category->id);
            }
        }

        $category->update([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        Session::flash('status', \trans('blog.manage.categories.updated'));

        return Response::redirect('/blog/manage/categories', 302);
    }

    public function destroy(string $id): Response
    {
        if (Session::authUserId() === null) {
            return Response::redirect('/login', 302);
        }

        $category = Category::find((int) $id);
        if ($category === null) {
            return View::html('errors.404', ['title' => \trans('errors.404.title')], 404);
        }

        if (! Csrf::validate()) {
            Session::flash('errors', ['_form' => \trans('auth.csrf_invalid')]);

            return Response::redirect('/blog/manage/categories', 302);
        }

        Post::query()->where('category_id', (int) $category->id)->update(['category_id' => null]);
        $category->delete();

        Session::flash('status', \trans('blog.manage.categories.deleted'));

        return Response::redirect('/blog/manage/categories', 302);
    }
}
