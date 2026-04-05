<?php

declare(strict_types=1);

use App\Controllers\AccountHandler;
use App\Controllers\Auth\LoginHandler;
use App\Controllers\BlogHandler;
use App\Controllers\BlogManageHandler;
use App\Controllers\Auth\LogoutHandler;
use App\Controllers\Auth\RegisterHandler;
use App\Controllers\HomeController;
use App\Middleware\GuestOnly;
use App\Middleware\RequireAuth;
use App\Middleware\ThrottleLogin;
use App\Middleware\ThrottleRegister;
use Vortex\Http\Response;
use Vortex\Routing\Route;

/**
 * HTTP route registration. Loaded automatically from `app/Routes/` (see {@see \Vortex\Routing\RouteDiscovery}).
 */

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/health', static fn (): Response => Response::json(['ok' => true]))->name('health');

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
