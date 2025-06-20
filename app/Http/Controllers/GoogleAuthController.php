<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Google\Client; // Pastikan ini ada
use Google\Service\Oauth2; // Pastikan ini ada
use Google\Service\Calendar; // Pastikan ini ada

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     *
     * @return RedirectResponse|Redirector
     */
    public function redirect(): RedirectResponse|Redirector
    {
        $client = $this->getClient(); // Memanggil getClient()

        $client->setScopes([
            Calendar::CALENDAR_EVENTS,
            Oauth2::USERINFO_PROFILE,
            Oauth2::USERINFO_EMAIL,
        ]);

        $authUrl = $client->createAuthUrl();

        return redirect($authUrl);
    }

    /**
     * Handle the Google authentication callback.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return RedirectResponse
     */
    public function callback(Request $request): RedirectResponse
    {
        $client = $this->getClient(); // Memanggil getClient()

        if ($request->has('code')) {
            $authCode = $request->input('code');

            // Exchange the authorization code for an access token
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            if (isset($accessToken['error'])) {
                return redirect('/')->with('error', 'Gagal mendapatkan token akses dari Google: ' . $accessToken['error_description']);
            }

            $client->setAccessToken($accessToken);

            session(['google_access_token' => $accessToken]);

            $oauth2Service = new Oauth2($client);
            $userInfo = $oauth2Service->userinfo->get();

            // Opsional: Simpan info user ke sesi
            session(['google_user_info' => $userInfo]);

            return redirect('/dashboard')->with('success', 'Berhasil terhubung dengan akun Google Anda!');
        } else {
            return redirect('/')->with('error', 'Autentikasi Google gagal atau ditolak.');
        }
    }

    /**
     * Helper function to get a configured Google Client.
     *
     * @return \Google\Client
     */
    private function getClient(): Client // <<< PASTIKAN METODE INI ADA DAN BENAR
    {
        $client = new Client(); // Gunakan fully qualified namespace untuk kejelasan
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $client->setAccessType('offline'); // Penting! Untuk mendapatkan refresh token
        $client->setPrompt('consent');     // Meminta user untuk selalu memberikan izin

        return $client;
    }

    /**
     * Get an authenticated Google Client instance.
     *
     * @return Client|RedirectResponse
     */
    public function getAuthenticatedClient(): Client|RedirectResponse
    {
        $client = $this->getClient();

        // Ambil token dari sesi. Di aplikasi nyata, ambil dari database yang terkait dengan user login.
        $accessToken = session('google_access_token');

        if ($accessToken) {
            $client->setAccessToken($accessToken);

            // Refresh token jika sudah expired
            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    session(['google_access_token' => $client->getAccessToken()]); // Update session
                } else {
                    // Refresh token tidak ada, user perlu otentikasi ulang
                    session()->forget('google_access_token');
                    return redirect('/google/auth')->with('error', 'Token Google expired. Harap otentikasi ulang.');
                }
            }
        } else {
            // Token tidak ada, user perlu otentikasi
            return redirect('/google/auth')->with('error', 'Harap hubungkan akun Google Anda.');
        }

        return $client;
    }

    /**
     * Log the user out of the Google session and revoke token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return RedirectResponse
     */
    public function logout(Request $request): RedirectResponse
    {
        try {
            $client = $this->getAuthenticatedClient();

            if ($client instanceof Client && $client->getAccessToken()) {
                $client->revokeToken();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error revoking Google token: " . $e->getMessage());
        }

        $request->session()->forget('google_access_token');
        $request->session()->forget('google_user_info');

        return redirect('/')->with('success', 'Anda telah berhasil logout dari akun Google Anda.');
    }
}