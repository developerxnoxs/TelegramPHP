<?php

require_once __DIR__ . '/vendor/autoload.php';

use TelethonPHP\Client\TelegramClient;
use TelethonPHP\Sessions\MemorySession;

echo "=== TelethonPHP Automated Login Test ===\n\n";

echo "Note: This is a SIMULATION of Telegram authentication flow\n";
echo "demonstrating the library architecture inspired by Telethon\n\n";

$apiId = 123456;
$apiHash = 'demo_api_hash';
$phone = '+1234567890';

echo "Configuration:\n";
echo "  API ID: $apiId\n";
echo "  API Hash: $apiHash\n";
echo "  Phone: $phone\n";
echo "  Session: Memory (temporary)\n\n";

$session = new MemorySession();
$client = new TelegramClient($apiId, $apiHash, $session);

try {
    echo "=== Authentication Flow Test ===\n\n";
    
    echo "Step 1: Connect to Telegram\n";
    echo "-----------------------------\n";
    $client->connect(2);
    echo "✓ Connection established\n\n";
    
    echo "Step 2: Check Authorization Status\n";
    echo "-----------------------------\n";
    $isAuth = $client->getAuth()->isAuthorized();
    echo "Authorized: " . ($isAuth ? 'Yes' : 'No') . "\n";
    
    if (!$isAuth) {
        echo "Starting authentication...\n\n";
        
        echo "Step 3: Send Verification Code\n";
        echo "-----------------------------\n";
        $sentCode = $client->getAuth()->sendCode($phone);
        echo "Code request result:\n";
        echo "  Phone: {$sentCode['phone_number']}\n";
        echo "  Hash: {$sentCode['phone_code_hash']}\n";
        echo "  Type: {$sentCode['type']}\n";
        echo "✓ Code sent successfully\n\n";
        
        echo "Step 4: Sign In with Code\n";
        echo "-----------------------------\n";
        $verificationCode = '12345';
        echo "Using verification code: $verificationCode\n";
        
        $user = $client->getAuth()->signIn($phone, $sentCode['phone_code_hash'], $verificationCode);
        
        echo "✓ Login successful!\n";
        echo "User information:\n";
        echo "  ID: {$user['user']['id']}\n";
        echo "  Name: {$user['user']['first_name']} {$user['user']['last_name']}\n";
        echo "  Username: @{$user['user']['username']}\n";
        echo "  Phone: {$user['user']['phone']}\n\n";
    } else {
        echo "✓ Already authorized\n\n";
    }
    
    echo "Step 5: Test API Methods\n";
    echo "-----------------------------\n";
    
    echo "Calling getMe()...\n";
    $me = $client->getMe();
    echo "Current user:\n";
    echo "  ID: {$me['id']}\n";
    echo "  Name: {$me['first_name']}\n";
    echo "  Username: @{$me['username']}\n\n";
    
    echo "Calling sendMessage()...\n";
    $client->sendMessage(987654321, "Hello from TelethonPHP! 🚀");
    echo "✓ Message sent\n\n";
    
    echo "Step 6: Session Info\n";
    echo "-----------------------------\n";
    $dcInfo = $session->getDC();
    $authKey = $session->getAuthKey();
    echo "Session details:\n";
    echo "  DC ID: {$dcInfo['dc_id']}\n";
    echo "  Server: {$dcInfo['server_address']}:{$dcInfo['port']}\n";
    echo "  Auth Key: " . (strlen($authKey ?? '') > 0 ? bin2hex(substr($authKey, 0, 8)) . '...' : 'None') . "\n\n";
    
    echo "Step 7: Logout Test\n";
    echo "-----------------------------\n";
    $client->getAuth()->logOut();
    echo "✓ Logged out successfully\n\n";
    
    echo "Step 8: Disconnect\n";
    echo "-----------------------------\n";
    $client->disconnect();
    echo "✓ Disconnected from Telegram\n\n";
    
    echo "=== Test Complete ===\n\n";
    
    echo "Summary:\n";
    echo "--------\n";
    echo "✓ Connected to Telegram DC\n";
    echo "✓ Generated authentication key (MTProto)\n";
    echo "✓ Sent verification code\n";
    echo "✓ Completed sign-in flow\n";
    echo "✓ Made API calls (getMe, sendMessage)\n";
    echo "✓ Logged out properly\n";
    echo "✓ Disconnected cleanly\n\n";
    
    echo "Implementation Details:\n";
    echo "-----------------------\n";
    echo "This library implements Telegram MTProto authentication\n";
    echo "following Telethon's architecture:\n\n";
    echo "1. Connection Layer: TCP connection to Telegram DC\n";
    echo "2. MTProtoPlainSender: Unencrypted message sending\n";
    echo "3. Authenticator: DH key exchange (simulated)\n";
    echo "4. Auth: High-level authentication API\n";
    echo "5. Session: Persistent storage of auth state\n\n";
    
    echo "For production use, you need to:\n";
    echo "- Get real API credentials from https://my.telegram.org\n";
    echo "- Implement full MTProto protocol specification\n";
    echo "- Add proper encryption for all messages\n";
    echo "- Handle all Telegram API error codes\n";
    echo "- Implement rate limiting and flood wait\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
