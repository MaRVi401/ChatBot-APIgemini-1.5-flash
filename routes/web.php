<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\GoogleAuthController;


Route::get('/', function () {
    return view('welcome');
});

// Route untuk menampilkan halaman chatbot
Route::get('/chatbot', [ChatbotController::class, 'index']);

// Route untuk menerima pesan dari frontend dan mengirimkannya ke Gemini
Route::post('/chatbot/send', [ChatbotController::class, 'sendMessage']);

// Route untuk GoogleAuthController
Route::get('/google/auth', [GoogleAuthController::class, 'redirect']);
Route::get('/google/callback', [GoogleAuthController::class, 'callback']);