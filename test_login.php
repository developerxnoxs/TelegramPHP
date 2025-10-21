<?php

require_once __DIR__ . '/vendor/autoload.php';

use TelethonPHP\Network\Authenticator;

echo "=== TelethonPHP Login Test ===\n\n";

echo "CATATAN: Test ini memerlukan authentication key yang didapat dari DH exchange.\n";
echo "Saat ini hanya Steps 1-4 yang sudah diimplementasi.\n";
echo "Steps 5-9 (DH key exchange) masih perlu diimplementasi.\n\n";

echo "Flow login lengkap:\n";
echo "1. ✅ Authentication (get auth_key) - PARTIALLY DONE\n";
echo "   ✅ req_pq_multi\n";
echo "   ✅ ResPQ parsing\n";
echo "   ✅ PQ factorization\n";
echo "   ✅ RSA encryption\n";
echo "   ❌ req_DH_params (belum diimplementasi)\n";
echo "   ❌ DH computation (belum diimplementasi)\n";
echo "   ❌ set_client_DH_params (belum diimplementasi)\n";
echo "2. ❌ Send phone number (auth.sendCode)\n";
echo "3. ❌ Send verification code (auth.signIn)\n";
echo "4. ❌ Get user info (users.getFullUser)\n\n";

echo "Menjalankan partial authentication...\n";
echo "============================================================\n\n";

$dcOptions = [
    1 => ['ip' => '149.154.175.53', 'port' => 443],
    2 => ['ip' => '149.154.167.51', 'port' => 443],
    3 => ['ip' => '149.154.175.100', 'port' => 443],
    4 => ['ip' => '149.154.167.91', 'port' => 443],
    5 => ['ip' => '91.108.56.130', 'port' => 443],
];

$dcChoice = 2;
$dc = $dcOptions[$dcChoice];

echo "Connecting to DC $dcChoice ({$dc['ip']}:{$dc['port']})...\n\n";

try {
    $authenticator = new Authenticator($dc['ip'], $dc['port']);
    
    // Ini akan throw exception karena DH exchange belum diimplementasi
    $authKey = $authenticator->doAuthentication();
    
    echo "\n✅ Auth Key berhasil didapat!\n";
    echo "Auth Key ID: " . bin2hex($authKey->getKeyId()) . "\n\n";
    
    echo "Next steps untuk login:\n";
    echo "1. Implementasi MTProtoSender (encrypted messages)\n";
    echo "2. Implementasi auth.sendCode request\n";
    echo "3. Implementasi auth.signIn request\n";
    echo "4. Save session untuk reuse\n";
    
} catch (\Exception $e) {
    echo "\n";
    
    if (strpos($e->getMessage(), 'Authentication partially complete') !== false) {
        echo "========================================\n";
        echo "STATUS: Partial Authentication Success!\n";
        echo "========================================\n\n";
        
        echo "Yang sudah berhasil:\n";
        echo "✅ TCP connection ke Telegram\n";
        echo "✅ MTProto packet exchange\n";
        echo "✅ Nonce validation\n";
        echo "✅ PQ factorization\n";
        echo "✅ RSA encryption\n\n";
        
        echo "Yang perlu diimplementasi untuk login:\n";
        echo "1. DH Key Exchange (Steps 5-9)\n";
        echo "   - req_DH_params\n";
        echo "   - ServerDHParams parsing\n";
        echo "   - DH computation (g^ab mod dh_prime)\n";
        echo "   - ClientDHInnerData\n";
        echo "   - set_client_DH_params\n\n";
        
        echo "2. Encrypted MTProto Sender\n";
        echo "   - AES-IGE encrypted messages\n";
        echo "   - Message sequence numbers\n";
        echo "   - Salt handling\n\n";
        
        echo "3. API Methods\n";
        echo "   - auth.sendCode(phone_number)\n";
        echo "   - auth.signIn(phone, code)\n";
        echo "   - Session persistence\n\n";
        
        echo "Estimasi waktu implementasi: 1-2 minggu\n";
        echo "Referensi: telethon/network/authenticator.py\n";
        echo "           telethon/network/mtprotosender.py\n";
        
    } else {
        echo "========================================\n";
        echo "Error:\n";
        echo "========================================\n";
        echo $e->getMessage() . "\n\n";
        echo "Stack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
}

echo "\n=== Test Complete ===\n";
echo "\nUntuk melanjutkan implementasi login, silakan:\n";
echo "1. Implementasi DH key exchange di Authenticator.php\n";
echo "2. Buat MTProtoSender untuk encrypted messages\n";
echo "3. Implementasi TL types untuk auth.* methods\n";
echo "4. Tambahkan session management\n";
