<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client as GuzzleClient; // Alias GuzzleHttp\Client untuk menghindari konflik dengan Google\Client
use Illuminate\Support\Facades\Log; // Untuk logging error
use Carbon\Carbon; // Untuk manipulasi tanggal/waktu
use App\Services\GoogleCalendarService; // Import service kalender kita
use App\Http\Controllers\GoogleAuthController; // Import controller autentikasi Google

class ChatbotController extends Controller
{
    
    public function index()
    {
        return view('chatbot');
    }

    /**
     * Mengirim pesan ke Gemini API dan mengembalikan balasan,
     * serta menangani penjadwalan rapat jika terdeteksi.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
            Log::error('Gemini API Key tidak dikonfigurasi.');
            return response()->json(['error' => 'Gemini API Key tidak dikonfigurasi. Harap periksa file .env Anda.'], 500);
        }

        $guzzleClient = new GuzzleClient(); // Gunakan alias GuzzleClient untuk permintaan HTTP
        // Gunakan model yang paling stabil atau sesuai akses Anda.
        // Coba 'gemini-1.5-pro-latest' atau 'gemini-1.0-pro' jika 'gemma-3-4b-it' bermasalah
        $model = 'gemma-3-4b-it'; 

        try {
            // --- KONTEKS WAKTU NYATA UNTUK GEMINI ---
            // Berikan tanggal dan waktu saat ini kepada Gemini agar bisa menghitung tanggal relatif.
            // Pastikan timezone di config/app.php sudah benar (misal: 'Asia/Jakarta')
            $currentDateTime = Carbon::now(config('app.timezone'));
            $currentDate = $currentDateTime->format('Y-m-d'); // YYYY-MM-DD
            $currentTime = $currentDateTime->format('H:i');   // HH:MM

            // --- Instruksi Sistem (Prompt Engineering) untuk Gemini ---
            $systemInstruction = [
                'role' => 'user', 
                'parts' => [
                    ['text' => "Anda adalah asisten AI serbaguna. Anda memiliki dua kemampuan utama:
                    1. Menjawab pertanyaan tentang alam, lingkungan, dan konservasi (flora, fauna, ekosistem, cuaca, geografi, bencana alam, upaya konservasi).
                    2. Membantu menjadwalkan rapat dan membuat tautan Google Meet.

                    **KONTEKS WAKTU SAAT INI: Hari ini adalah {$currentDate}, Pukul {$currentTime} WIB.**

                    Prioritaskan penjadwalan rapat jika pesan pengguna mengandung niat tersebut.

                    Jika pengguna meminta penjadwalan rapat, identifikasi niatnya. Ekstrak informasi berikut:
                    - **topic**: Judul rapat (string).
                    - **date**: Tanggal rapat dalam format 'YYYY-MM-DD'. **Hitung tanggal ini relatif terhadap hari ini ({$currentDate})**. Jika hanya 'besok', hitung tanggal besok. Jika hanya hari dalam seminggu ('Senin', 'Selasa', dst.), gunakan hari tersebut *terdekat setelah atau pada* hari ini. Jika tidak ada tanggal, gunakan hari ini ({$currentDate}).
                    - **time**: Waktu rapat dalam format 'HH:MM' (24-jam). Jika tidak ada waktu, default ke '09:00'.
                    - **duration_minutes**: Durasi rapat dalam menit (integer). Default ke 60 menit jika tidak disebutkan.
                    - **attendees**: Array alamat email peserta (contoh: ['email1@example.com', 'email2@example.com']). Jika tidak ada, biarkan array kosong.

                    Berikan respons untuk permintaan penjadwalan dalam format JSON. Pastikan JSON valid dan hanya berisi objek JSON:
                    ```json
                    {
                        \"intent\": \"schedule_meeting\",
                        \"data\": {
                            \"topic\": \"string\",
                            \"date\": \"YYYY-MM-DD\",
                            \"time\": \"HH:MM\",
                            \"duration_minutes\": \"integer\",
                            \"attendees\": [\"string\"]
                        }
                    }
                    ```
                    Jika ada informasi yang tidak dapat diekstrak atau tidak lengkap, gunakan nilai default atau kosong, tetapi sertakan \"intent\": \"schedule_meeting\".

                    Untuk pertanyaan lain yang tidak berhubungan dengan penjadwalan, respons seperti biasa sebagai chatbot alam. Jika pertanyaan tidak berhubungan dengan topik alam atau penjadwalan, respons dengan jelas bahwa Anda hanya bisa membahas topik tersebut.
                    "]
                ]
            ];

            // Konfirmasi dari model (opsional tapi baik untuk alur percakapan)
            $modelConfirmation = [
                'role' => 'model',
                'parts' => [
                    ['text' => 'Baik, saya mengerti. Saya siap membantu Anda dengan pertanyaan seputar alam dan lingkungan, serta menjadwalkan rapat!']
                ]
            ];

            // Pesan pengguna saat ini
            $currentUserMessage = [
                'role' => 'user',
                'parts' => [['text' => $userMessage]]
            ];

            // Seluruh riwayat percakapan yang dikirim ke Gemini
            $contents = [
                $systemInstruction,
                $modelConfirmation,
                $currentUserMessage
                // Anda bisa menambahkan riwayat percakapan sebelumnya di sini
                // untuk konteks yang lebih baik, jika Anda menyimpannya di sesi/database.
            ];

            // Lakukan permintaan POST ke Gemini API
            $response = $guzzleClient->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'json' => [
                    'contents' => $contents // Gunakan array 'contents' yang sudah dimodifikasi
                ]
            ]);

            // Decode respons JSON dari Gemini
            $body = json_decode($response->getBody()->getContents(), true);

            $geminiResponseText = 'Maaf, saya tidak mengerti atau ada masalah dengan balasan Gemini.';
            $isSchedulingIntent = false;
            $schedulingData = [];

            // Periksa apakah balasan dari Gemini valid dan ekstrak teks atau data JSON
            if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
                $rawGeminiResponseText = $body['candidates'][0]['content']['parts'][0]['text'];

                // --- EKSTRAKSI JSON DARI MARKDOWN (JIKA ADA) ---
                $jsonString = '';
                // Coba temukan blok JSON yang dibungkus markdown (```json ... ```)
                if (preg_match('/```json\s*(.*?)\s*```/s', $rawGeminiResponseText, $matches)) {
                    $jsonString = trim($matches[1]); // Ambil konten di antara ```json dan ```
                } else {
                    // Jika tidak dibungkus markdown, asumsikan itu JSON murni
                    $jsonString = trim($rawGeminiResponseText);
                }
                // --- AKHIR EKSTRAKSI ---

                // Coba parse string JSON yang sudah diekstrak
                $parsedGeminiResponse = json_decode($jsonString, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($parsedGeminiResponse['intent']) && $parsedGeminiResponse['intent'] === 'schedule_meeting') {
                    $isSchedulingIntent = true;
                    $schedulingData = $parsedGeminiResponse['data'];
                } else {
                    // Jika json_decode gagal atau bukan intent scheduling,
                    // maka ini adalah respons teks biasa (non-JSON) dari Gemini.
                    // Gunakan rawGeminiResponseText sebagai balasan biasa.
                    $geminiResponseText = $rawGeminiResponseText;
                }
            }
            // Tangani jika Gemini memblokir respons (misalnya karena prompt feedback)
            else if (isset($body['promptFeedback']['blockReason'])) {
                $blockReason = $body['promptFeedback']['blockReason'];
                Log::warning("Gemini blocked response: " . $blockReason . " for message: " . $userMessage);
                $geminiResponseText = "Maaf, saya tidak dapat memproses pesan Anda saat ini. Mungkin ada masalah dengan pertanyaan Anda. Silakan coba pertanyaan lain yang relevan dengan topik alam atau permintaan penjadwalan.";
            }

            // --- Logika Penanganan Penjadwalan Berdasarkan Respons Gemini ---
            if ($isSchedulingIntent) {
                // Dapatkan klien Google yang terotentikasi
                $googleAuth = new GoogleAuthController();
                $client = $googleAuth->getAuthenticatedClient();

                // Pastikan klien adalah instance Google\Client, bukan RedirectResponse
                if ($client instanceof \Illuminate\Http\RedirectResponse) {
                    // Jika user belum otentikasi Google, berikan pesan dan instruksi.
                    // Ini akan menghasilkan tautan HTML di frontend.
                    return response()->json(['reply' => 'Maaf, untuk menjadwalkan rapat, Anda perlu menghubungkan akun Google Anda terlebih dahulu. Silakan kunjungi halaman <a href="' . url('/google/auth') . '" target="_blank">autentikasi Google</a>.']);
                }

                // Inisialisasi GoogleCalendarService dengan klien yang terotentikasi
                $calendarService = new GoogleCalendarService($client);

                // Ekstrak data dari respons Gemini yang sudah di-parse
                $summary = $schedulingData['topic'] ?? 'Rapat dari Chatbot'; // Default topik
                $description = 'Rapat yang dijadwalkan oleh AI Assistant Anda.';
                $attendees = $schedulingData['attendees'] ?? []; // Default peserta kosong

                // --- Parsing Tanggal dan Waktu dari Data yang Disediakan Gemini ---
                $date = $schedulingData['date'] ?? null;
                $time = $schedulingData['time'] ?? '09:00'; // Default waktu
                $duration = $schedulingData['duration_minutes'] ?? 60; // Default durasi

                $parsedDate = null;
                // Coba parse tanggal yang diberikan Gemini
                if ($date) {
                    try {
                        $parsedDate = Carbon::parse($date, config('app.timezone'));
                        // Heuristik opsional: Jika tanggal dari Gemini adalah di masa lalu,
                        // dan tanggal di prompt asli adalah relatif, coba perbaiki.
                        // Namun, dengan prompt yang lebih baik di Gemini, ini harusnya jarang diperlukan.
                        if ($parsedDate->isPast() && !preg_match('/\d{4}-\d{2}-\d{2}/', $userMessage)) {
                            // Jika tanggal yang diparse di masa lalu DAN user tidak memberikan tanggal spesifik (misal "besok")
                            // Coba geser ke tahun ini atau tahun depan jika masih di masa lalu
                            if ($parsedDate->year < Carbon::now()->year) {
                                $parsedDate->addYears(Carbon::now()->year - $parsedDate->year);
                            }
                            // Jika setelah penyesuaian tahun masih di masa lalu, mungkin maksudnya tahun depan
                            if ($parsedDate->isPast()) { 
                                $parsedDate->addYear();
                            }
                            Log::warning("Adjusted parsed date from {$date} to {$parsedDate->format('Y-m-d')} as it was in the past, based on relative prompt.");
                        }
                    } catch (\Exception $e) {
                        Log::warning("Gemini returned unparsable date '{$date}', defaulting to today. Error: " . $e->getMessage());
                        $parsedDate = Carbon::now(config('app.timezone')); // Fallback ke hari ini jika parsing gagal
                    }
                } else {
                    // Jika Gemini tidak memberikan tanggal sama sekali, default ke hari ini
                    $parsedDate = Carbon::now(config('app.timezone'));
                }

                // Gabungkan tanggal dan waktu yang sudah di-parse
                $startDateTime = $parsedDate->setTimeFromTimeString($time);
                $endDateTime = $startDateTime->copy()->addMinutes($duration);

                // Format untuk Google Calendar API (ISO 8601)
                $startTimeFormatted = $startDateTime->format('Y-m-d\TH:i:s');
                $endTimeFormatted = $endDateTime->format('Y-m-d\TH:i:s');

                // --- Panggil Service Kalender untuk membuat event ---
                $event = $calendarService->createEvent(
                    $summary,
                    $description,
                    $startTimeFormatted,
                    $endTimeFormatted,
                    $attendees,
                    true // true untuk menambahkan link Google Meet secara otomatis
                );

                if ($event) {
                    // Ekstrak tautan Google Meet dari respons event yang berhasil dibuat
                    $meetLink = '';
                    if ($event->getConferenceData() && $event->getConferenceData()->getEntryPoints()) {
                        foreach ($event->getConferenceData()->getEntryPoints() as $entryPoint) {
                            if ($entryPoint->getEntryPointType() === 'video') {
                                $meetLink = $entryPoint->getUri();
                                break;
                            }
                        }
                    }

                    // Buat balasan sukses yang ramah pengguna untuk frontend
                    $reply = "Rapat berjudul '{$event->getSummary()}' berhasil dijadwalkan pada " .
                             Carbon::parse($event->getStart()->getDateTime())->format('d M Y H:i') . " WIB.";
                    if ($meetLink) {
                        $reply .= " Tautan Google Meet: <a href='{$meetLink}' target='_blank'>{$meetLink}</a>";
                    }
                    $reply .= " Silakan periksa Google Calendar Anda.";

                    return response()->json(['reply' => $reply]);
                } else {
                    // Jika pembuatan event gagal (misalnya karena masalah API Google Calendar)
                    Log::error("Failed to create Google Calendar event for user: " . ($request->user()->id ?? 'Guest') . ". Scheduling data: " . json_encode($schedulingData));
                    return response()->json(['reply' => 'Maaf, saya gagal menjadwalkan rapat. Mungkin ada masalah dengan izin, format waktu, atau API Google Calendar. Silakan coba lagi atau periksa log server untuk detail lebih lanjut.']);
                }
            } else {
                // Jika Gemini tidak mengidentifikasi niat penjadwalan,
                // atau jika parsing JSON gagal atau intent bukan "schedule_meeting",
                // maka kembalikan respons teks asli dari Gemini.
                return response()->json(['reply' => $geminiResponseText]);
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Tangani error yang berasal dari Gemini API (misal: 4xx atau 5xx status code)
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = $e->getResponse()->getBody()->getContents();
            Log::error('Gemini API Client Error (' . $statusCode . '): ' . $responseBody . ' for message: ' . $userMessage);
            return response()->json(['error' => 'Error dari Gemini API: ' . $e->getMessage() . ' (Status: ' . $statusCode . '). Silakan coba lagi nanti.'], $statusCode);
        } catch (\Exception $e) {
            // Tangani error umum lainnya yang tidak terduga
            Log::error('Kesalahan Chatbot tidak terduga: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
            return response()->json(['error' => 'Terjadi kesalahan tak terduga: ' . $e->getMessage()], 500);
        }
    }
}