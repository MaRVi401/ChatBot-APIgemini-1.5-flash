<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response; // Tetap sertakan Response jika ada logika lain yang mengembalikannya
use Illuminate\Http\RedirectResponse; // Tambahkan ini
use Illuminate\Routing\Redirector;   // Tambahkan ini (opsional, tapi bagus untuk presisi)
use Google\Client;
use Google\Service\Oauth2;
use Google\Service\Calendar;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     *
     * @return RedirectResponse|Redirector
     */
    public function redirect(): RedirectResponse|Redirector // Di sini kita pakai Union Type
    {
        $client = $this->getClient();

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
        $client = $this->getClient();

        if ($request->has('code')) {
            $authCode = $request->input('code');
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            if (isset($accessToken['error'])) {
                return redirect('/')->with('error', 'Gagal mendapatkan token akses dari Google: ' . $accessToken['error_description']);
            }

            $client->setAccessToken($accessToken);
            session(['google_access_token' => $accessToken]);

            $oauth2Service = new Oauth2($client);
            $userInfo = $oauth2Service->userinfo->get();

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
    private function getClient(): Client // Tipe hint yang sudah benar
    {
        $client = new Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    /**
     * Contoh: Mengambil Google Client dengan token yang sudah ada (dari session/database).
     * Ini akan Anda gunakan di Controller lain untuk berinteraksi dengan API.
     *
     * @param array|null $accessToken
     * @return Client|RedirectResponse // Di sini kita pakai Union Type
     */
    public function getAuthenticatedClient($accessToken = null): Client|RedirectResponse
    {
        $client = $this->getClient();

        if (!$accessToken && session()->has('google_access_token')) {
            $accessToken = session('google_access_token');
        }

        if ($accessToken) {
            $client->setAccessToken($accessToken);

            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    session(['google_access_token' => $client->getAccessToken()]);
                } else {
                    session()->forget('google_access_token');
                    return redirect('/google/auth')->with('error', 'Token Google expired. Harap otentikasi ulang.');
                }
            }
        } else {
            return redirect('/google/auth')->with('error', 'Harap hubungkan akun Google Anda.');
        }

        return $client;
    }
}