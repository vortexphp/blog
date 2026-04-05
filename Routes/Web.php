<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\Auth\LoginController;
use App\Controllers\BlogCategoryManageController;
use App\Controllers\BlogController;
use App\Controllers\BlogManageController;
use App\Controllers\Auth\LogoutController;
use App\Controllers\Auth\RegisterController;
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

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/category/{slug}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/manage', [BlogManageController::class, 'index'], [RequireAuth::class])->name('blog.manage.index');
Route::get('/blog/manage/categories', [BlogCategoryManageController::class, 'index'], [RequireAuth::class])->name('blog.manage.categories');
Route::get('/blog/manage/categories/new', [BlogCategoryManageController::class, 'create'], [RequireAuth::class]);
Route::post('/blog/manage/categories', [BlogCategoryManageController::class, 'store'], [RequireAuth::class]);
Route::get('/blog/manage/categories/{id}/edit', [BlogCategoryManageController::class, 'edit'], [RequireAuth::class]);
Route::post('/blog/manage/categories/{id}', [BlogCategoryManageController::class, 'update'], [RequireAuth::class]);
Route::post('/blog/manage/categories/{id}/delete', [BlogCategoryManageController::class, 'destroy'], [RequireAuth::class]);
Route::get('/blog/manage/posts/new', [BlogManageController::class, 'create'], [RequireAuth::class]);
Route::post('/blog/manage/posts', [BlogManageController::class, 'store'], [RequireAuth::class]);
Route::get('/blog/manage/posts/{id}/edit', [BlogManageController::class, 'edit'], [RequireAuth::class]);
Route::post('/blog/manage/posts/{id}', [BlogManageController::class, 'update'], [RequireAuth::class]);
Route::post('/blog/manage/posts/{id}/delete', [BlogManageController::class, 'destroy'], [RequireAuth::class]);
Route::post('/blog/{slug}/comments', [BlogController::class, 'storeComment']);
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/register', [RegisterController::class, 'show'], [GuestOnly::class])
    ->name('register.show')
    ->post('/register', [RegisterController::class, 'store'], [GuestOnly::class, ThrottleRegister::class])
    ->name('register.store');

Route::get('/login', [LoginController::class, 'show'], [GuestOnly::class])
    ->name('login.show')
    ->post('/login', [LoginController::class, 'store'], [GuestOnly::class, ThrottleLogin::class])
    ->name('login.store');

Route::post('/logout', [LogoutController::class, 'store'])->name('logout.store');

Route::get('/account', [AccountController::class, 'index'], [RequireAuth::class])->name('account.index');
Route::get('/account/edit', [AccountController::class, 'edit'], [RequireAuth::class])
    ->name('account.edit')
    ->post('/account/edit', [AccountController::class, 'update'], [RequireAuth::class])
    ->name('account.update');
