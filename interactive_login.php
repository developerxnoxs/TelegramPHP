<?php

require_once __DIR__ . '/vendor/autoload.php';

use XnoxsProto\Client\TelegramClient;
use XnoxsProto\Sessions\FileSession;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         XnoxsProto - Interactive Telegram Login               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$sessionFile = 'my_session.json';

echo "📝 Anda memerlukan API credentials dari https://my.telegram.org/apps\n\n";

echo "Masukkan API ID Anda: ";
$apiId = (int)trim(fgets(STDIN));

echo "Masukkan API Hash Anda: ";
$apiHash = trim(fgets(STDIN));

// Check if session file exists
if (file_exists($sessionFile)) {
    echo "\n🔍 Session file ditemukan! Mencoba menggunakan session yang ada...\n";
    
    try {
        $session = new FileSession($sessionFile);
        $client = new TelegramClient($apiId, $apiHash, $session);
        
        echo "\n" . str_repeat("=", 64) . "\n";
        echo "🔌 Connecting to Telegram...\n";
        echo str_repeat("=", 64) . "\n\n";
        
        $client->connect();
        
        if ($client->getAuth()->isAuthorized()) {
            echo "\n" . str_repeat("=", 64) . "\n";
            echo "✅ SUDAH LOGIN!\n";
            echo str_repeat("=", 64) . "\n\n";
            echo "🎉 Anda sudah login menggunakan session yang tersimpan.\n";
            echo "💡 Session Anda masih aktif, tidak perlu login ulang.\n\n";
            
            $client->disconnect();
            exit(0);
        } else {
            echo "⚠️  Session tidak valid atau sudah expired.\n";
            echo "📱 Melanjutkan proses login ulang...\n\n";
        }
    } catch (\Exception $e) {
        echo "⚠️  Error menggunakan session lama: " . $e->getMessage() . "\n";
        echo "📱 Melanjutkan proses login ulang...\n\n";
    }
}

echo "Masukkan nomor telepon Anda (dengan kode negara, contoh: +628123456789): ";
$phoneNumber = trim(fgets(STDIN));

echo "\n" . str_repeat("=", 64) . "\n";
echo "🚀 Memulai koneksi ke Telegram...\n";
echo str_repeat("=", 64) . "\n\n";

try {
    $session = new FileSession($sessionFile);
    $client = new TelegramClient($apiId, $apiHash, $session);
    
    echo "🔌 Connecting to Telegram...\n";
    $client->connect();
    
    echo "\n📱 Mengirim kode verifikasi ke $phoneNumber...\n";
    $sentCode = $client->getAuth()->sendCode($phoneNumber);
    
    echo "\n✅ Kode telah dikirim!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📨 Silakan cek SMS atau aplikasi Telegram Anda\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "Masukkan kode verifikasi yang Anda terima: ";
    $code = trim(fgets(STDIN));
    
    echo "\n🔐 Melakukan login...\n";
    $user = $client->getAuth()->signIn(
        $phoneNumber,
        $sentCode['phone_code_hash'],
        $code
    );
    
    echo "\n" . str_repeat("=", 64) . "\n";
    echo "✅ LOGIN BERHASIL!\n";
    echo str_repeat("=", 64) . "\n\n";
    
    echo "📋 Informasi Akun Anda:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "👤 User ID       : " . $user['user']['id'] . "\n";
    echo "📱 Phone         : " . $user['user']['phone'] . "\n";
    
    if (isset($user['user']['first_name'])) {
        echo "👋 First Name    : " . $user['user']['first_name'] . "\n";
    }
    
    if (isset($user['user']['last_name']) && $user['user']['last_name']) {
        echo "📝 Last Name     : " . $user['user']['last_name'] . "\n";
    }
    
    if (isset($user['user']['username']) && $user['user']['username']) {
        echo "🔗 Username      : @" . $user['user']['username'] . "\n";
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    echo "\n🔌 Disconnecting...\n";
    $client->disconnect();
    
    echo "\n✅ Selesai! Session telah disimpan ke 'my_session.json'\n";
    echo "💡 Anda dapat menggunakan session ini untuk login selanjutnya tanpa kode\n\n";
    
} catch (\Exception $e) {
    echo "\n" . str_repeat("=", 64) . "\n";
    echo "❌ ERROR\n";
    echo str_repeat("=", 64) . "\n\n";
    
    echo "Pesan Error: " . $e->getMessage() . "\n\n";
    
    if (getenv('DEBUG') === 'true') {
        echo "Stack Trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
    exit(1);
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                  Terima kasih!                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";
