<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('google_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Jika Anda punya sistem user
            $table->string('google_id')->unique(); // ID unik dari Google
            $table->text('access_token');
            $table->text('refresh_token')->nullable(); // Refresh token penting untuk akses offline
            $table->dateTime('expires_at'); // Kapan access token akan expired
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_accounts');
    }
};