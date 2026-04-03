<?php

declare(strict_types=1);

use App\Handlers\AccountHandler;
use App\Handlers\Auth\LoginHandler;
use App\Handlers\BlogHandler;
use App\Handlers\BlogManageHandler;
use App\Handlers\Auth\LogoutHandler;
use App\Handlers\Auth\RegisterHandler;
use App\Handlers\DocsHandler;
use App\Handlers\HomeHandler;
use App\Middleware\GuestOnly;
use App\Middleware\RequireAuth;
use App\Middleware\ThrottleLogin;
use App\Middleware\ThrottleRegister;
use Vortex\Http\Response;
use Vortex\Routing\Route;

/**
 * HTTP route registration. Loaded automatically from `app/Routes/` (see {@see \Vortex\Routing\RouteDiscovery}).
 */

Route::get('/', [HomeHandler::class, 'index'])->name('home');
Route::get('/health', static fn (): Response => Response::json(['ok' => true]))->name('health');

Route::get('/docs', [DocsHandler::class, 'index'])->name('docs.index');
Route::get('/docs/{path...}', [DocsHandler::class, 'show'])->name('docs.show');

Route::get('/blog', [BlogHandler::class, 'index'])->name('blog.index');
Route::get('/blog/manage', [BlogManageHandler::class, 'index'], [RequireAuth::class])->name('blog.manage.index');
Route::get('/blog/manage/posts/new', [BlogManageHandler::class, 'create'], [RequireAuth::class]);
Route::post('/blog/manage/posts', [BlogManageHandler::class, 'store'], [RequireAuth::class]);
Route::get('/blog/manage/posts/{id}/edit', [BlogManageHandler::class, 'edit'], [RequireAuth::class]);
Route::post('/blog/manage/posts/{id}', [BlogManageHandler::class, 'update'], [RequireAuth::class]);
Route::post('/blog/manage/posts/{id}/delete', [BlogManageHandler::class, 'destroy'], [RequireAuth::class]);
Route::post('/blog/{slug}/comments', [BlogHandler::class, 'storeComment']);
Route::get('/blog/{slug}', [BlogHandler::class, 'show'])->name('blog.show');

Route::get('/register', [RegisterHandler::class, 'show'], [GuestOnly::class])
    ->name('register.show')
    ->post('/register', [RegisterHandler::class, 'store'], [GuestOnly::class, ThrottleRegister::class])
    ->name('register.store');

Route::get('/login', [LoginHandler::class, 'show'], [GuestOnly::class])
    ->name('login.show')
    ->post('/login', [LoginHandler::class, 'store'], [GuestOnly::class, ThrottleLogin::class])
    ->name('login.store');

Route::post('/logout', [LogoutHandler::class, 'store'])->name('logout.store');

Route::get('/account', [AccountHandler::class, 'index'], [RequireAuth::class])->name('account.index');
Route::get('/account/edit', [AccountHandler::class, 'edit'], [RequireAuth::class])
    ->name('account.edit')
    ->post('/account/edit', [AccountHandler::class, 'update'], [RequireAuth::class])
    ->name('account.update');
