<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReservationController;


Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('resend-verification', [AuthController::class, 'resendVerification']);


// Payment Callback
Route::post('/payment/callback', [ReservationController::class, 'callback']);


// Public Can See All Room Listing
Route::get('/public/rooms', [RoomController::class, 'publicIndex']);
Route::get('public/rooms/{id}', [RoomController::class, 'show']);


// All Roles
Route::middleware(['auth:api', 'jwt.refresh'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // universal untuk semua role
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::post('/profile/avatar', [UserController::class, 'uploadAvatar']);
});


// User Only
Route::middleware(['auth:api', 'jwt.refresh', 'role:user'])->group(function () {
    Route::post('/book', [ReservationController::class, 'store']);
    Route::get('/my-bookings', [ReservationController::class, 'myBooking']);
    Route::get('/my-bookings/{id}', [ReservationController::class, 'show']);
    Route::delete('my-bookings/{id}', [ReservationController::class, 'cancel']);
});


// Admin & Staff
Route::middleware(['auth:api', 'jwt.refresh', 'role:admin,staff'])->group(function () {

    // Rooms CRUD
    Route::apiResource('/rooms', RoomController::class);

    // Bookings Management
    Route::get('/bookings', [ReservationController::class, 'index']);
    Route::get('.bookings/{id}', [ReservationController::class, 'show']);
    Route::put('/bookings/{id}', [ReservationController::class, 'updateStatus']);
});


// Admin Only
Route::middleware(['auth:api', 'jwt.refresh', 'role:admin'])->group(function () {

    // User Management
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::post('/admin/staff', [UserController::class, 'storeStaff']);
    Route::put('/admin/user/{id}', [UserController::class, 'update']);
    Route::delete('/admin/user/{id}', [UserController::class, 'destroy']);

    // Reservations
    Route::get('/admin/reservations', [ReservationController::class, 'all']);

    // Dashborad Stats
    Route::get('/admin/stats', [ReservationController::class, 'stats']);
});
