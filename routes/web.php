<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;

Route::get('/', function () {
    return view('welcome');
});

// Route untuk menampilkan halaman chatbot
Route::get('/chatbot', [ChatbotController::class, 'index']);

// Route untuk menerima pesan dari frontend dan mengirimkannya ke Gemini
Route::post('/chatbot/send', [ChatbotController::class, 'sendMessage']);