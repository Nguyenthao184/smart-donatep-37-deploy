<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TroChuyenController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DonateController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-chat/{cuoc_tro_chuyen}', function ($id) {
    return view('test-chat', ['cuoc_tro_chuyen_id' => $id]);
});

Route::get('/verify-register', [AuthController::class, 'verifyRegister']);

Route::get('/vnpay/return', [DonateController::class, 'vnpayReturn']);