<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log; // Untuk logging error

class ChatbotController extends Controller
{
    // Menampilkan halaman chatbot
    public function index()
    {
        return view('chatbot');
    }

    // Mengirim pesan ke Gemini API dan mengembalikan balasan
    public function sendMessage(Request $request)
    {
        // Validasi input: pastikan 'message' ada dan berupa string
        $request->validate([
            'message' => 'required|string',
        ]);

        $userMessage = $request->input('message');
        $apiKey = env('GEMINI_API_KEY'); // Ambil API Key dari .env

        // Pastikan API Key sudah dikonfigurasi
        if (!$apiKey) {
            return response()->json(['error' => 'Gemini API Key tidak dikonfigurasi. Harap periksa file .env Anda.'], 500);
        }

        $client = new Client();
        $model = 'gemini-1.5-flash'; // Model Gemini yang akan digunakan

        try {
            // --- Bagian yang dimodifikasi: Menyiapkan array 'contents' dengan instruksi sistem ---
            $contents = [
                // Ini adalah instruksi sistem yang paling penting.
                // Tempatkan instruksi ini sebagai pesan pertama dari 'user'
                // diikuti dengan balasan 'model' (opsional, tapi baik untuk konfirmasi peran).
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'Anda adalah chatbot yang sangat fokus pada topik alam, lingkungan, dan konservasi. Anda hanya akan menjawab pertanyaan yang berhubungan dengan alam, seperti flora, fauna, ekosistem, cuaca, geografi, bencana alam, lingkungan, atau upaya konservasi. Jika pertanyaan tidak berhubungan dengan topik ini (misalnya tentang olahraga, sejarah non-alam, selebriti, teknologi umum, politik, atau topik di luar lingkup alam), mohon respons dengan jelas bahwa Anda hanya bisa membahas topik alam dan tidak bisa menjawab pertanyaan tersebut. Jangan memberikan informasi di luar topik alam.']
                    ]
                ],
                [
                    'role' => 'model',
                    'parts' => [
                        ['text' => 'Baik, saya mengerti. Saya siap membantu Anda dengan pertanyaan seputar alam dan lingkungan!']
                    ]
                ],
                // Tambahkan pesan pengguna saat ini sebagai bagian dari percakapan.
                // Jika Anda ingin mempertahankan riwayat percakapan yang lebih panjang
                // (misalnya untuk konteks), Anda akan mengambil riwayat dari sesi
                // atau database dan menambahkannya di sini sebelum pesan user saat ini.
                [
                    'role' => 'user',
                    'parts' => [['text' => $userMessage]]
                ]
            ];
            // --- Akhir bagian yang dimodifikasi ---

            // Lakukan permintaan POST ke Gemini API
            $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'json' => [
                    'contents' => $contents // Gunakan array 'contents' yang sudah dimodifikasi
                ]
            ]);

            // Decode respons JSON dari Gemini
            $body = json_decode($response->getBody()->getContents(), true);

            $geminiResponse = 'Maaf, saya tidak mengerti atau ada masalah dengan balasan Gemini.';
            // Periksa apakah balasan dari Gemini valid
            if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
                $geminiResponse = $body['candidates'][0]['content']['parts'][0]['text'];
            }
            // Tangani jika Gemini memblokir respons (misalnya karena prompt feedback)
            else if (isset($body['promptFeedback']['blockReason'])) {
                $blockReason = $body['promptFeedback']['blockReason'];
                Log::warning("Gemini blocked response: " . $blockReason . " for message: " . $userMessage);
                $geminiResponse = "Maaf, saya tidak dapat memproses pesan Anda saat ini. Mungkin ada masalah dengan pertanyaan Anda. Silakan coba pertanyaan lain yang relevan dengan alam.";
            }


            // Kirim balasan Gemini kembali ke frontend
            return response()->json(['reply' => $geminiResponse]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Tangani error yang berasal dari Gemini API (misal: 4xx atau 5xx status code)
            $responseBody = $e->getResponse()->getBody()->getContents();
            Log::error('Gemini API Client Error: ' . $responseBody);
            // Anda bisa coba parse $responseBody untuk pesan error yang lebih spesifik jika Gemini menyediakannya
            return response()->json(['error' => 'Error dari Gemini API: ' . $e->getMessage()], $e->getResponse()->getStatusCode());
        } catch (\Exception $e) {
            // Tangani error umum lainnya
            Log::error('Kesalahan Chatbot: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan tak terduga: ' . $e->getMessage()], 500);
        }
    }
}