<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Anda</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Selamat Datang di Dashboard!</h1>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="error">
            {{ session('error') }}
        </div>
    @endif

    <p>Anda berhasil terhubung dengan Google.</p>

    @if(isset($googleAccessToken))
        <p>Token Akses Google Anda (disimpan di sesi):</p>
        <pre>{{ json_encode($googleAccessToken, JSON_PRETTY_PRINT) }}</pre>
        <p>Biasanya, Anda akan menyimpan ini ke database, bukan hanya sesi.</p>
    @else
        <p>Token Akses Google tidak ditemukan di sesi.</p>
    @endif

    <a href="{{ route('chatbot') }}">chatbot</a>

    <a href="{{ route('google.logout') }}">Logout dari Google</a>

</body>
</html>