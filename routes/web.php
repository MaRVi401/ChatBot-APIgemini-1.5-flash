<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\GoogleAuthController;


Route::get('/', function () {
    return view('welcome');
});

// Route untuk menampilkan halaman chatbot
Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot');

// Route untuk menerima pesan dari frontend dan mengirimkannya ke Gemini
Route::post('/chatbot/send', [ChatbotController::class, 'sendMessage']);

// Route untuk GoogleAuthController
Route::get('/google/auth', [GoogleAuthController::class, 'redirect']);
Route::get('/google/callback', [GoogleAuthController::class, 'callback']);
Route::get('/google/logout', [GoogleAuthController::class, 'logout'])->name('google.logout');

// --- Tambahkan ini ---
Route::get('/dashboard', function () {
    // Mengambil data dari sesi di sini,
    // misalnya untuk menampilkan nama user atau email Google mereka
    $googleAccessToken = session('google_access_token');
    $userInfo = session('google_user_info'); // Jika Anda menyimpan info user

    return view('dashboard', compact('googleAccessToken', 'userInfo'));
})->name('dashboard'); // Memberi nama rute ini adalah praktik yang baik
// --- Akhir tambahan ---