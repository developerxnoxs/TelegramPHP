<?php

require_once __DIR__ . '/vendor/autoload.php';

use TelethonPHP\Client\TelegramClient;
use TelethonPHP\Sessions\FileSession;

echo "=== TelethonPHP - Real Telegram Login Test ===\n\n";

echo "📝 This will test REAL login to Telegram!\n";
echo "You need to get API credentials from https://my.telegram.org/apps\n\n";

echo "Enter your API ID: ";
$apiId = (int)trim(fgets(STDIN));

echo "Enter your API Hash: ";
$apiHash = trim(fgets(STDIN));

echo "Enter your phone number (with country code, e.g., +1234567890): ";
$phone = trim(fgets(STDIN));

echo "\n" . str_repeat("=", 60) . "\n";
echo "Starting Telegram Client...\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $session = new FileSession('test_session.json');
    $client = new TelegramClient($apiId, $apiHash, $session);
    
    echo "\n[Test] Connecting to Telegram...\n";
    $client->connect();
    
    echo "\n[Test] Attempting to login...\n";
    
    echo "\n[Test] Sending auth code to $phone...\n";
    $sentCode = $client->getAuth()->sendCode($phone);
    
    echo "\n✅ Code sent successfully!\n";
    echo "Phone Code Hash: " . substr($sentCode['phone_code_hash'], 0, 20) . "...\n\n";
    
    echo "Enter the code you received (via SMS or Telegram app): ";
    $code = trim(fgets(STDIN));
    
    echo "\n[Test] Signing in with code...\n";
    $user = $client->getAuth()->signIn($phone, $sentCode['phone_code_hash'], $code);
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ LOGIN SUCCESSFUL!\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "User Information:\n";
    echo "  User ID: " . $user['user']['id'] . "\n";
    echo "  Phone: " . $user['user']['phone'] . "\n";
    echo "  Authorized: " . ($user['user']['authorized'] ? 'Yes' : 'No') . "\n";
    
    echo "\n[Test] Disconnecting...\n";
    $client->disconnect();
    
    echo "\n✅ Test completed successfully!\n";
    
} catch (\Exception $e) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "❌ ERROR\n";
    echo str_repeat("=", 60) . "\n\n";
    
    echo "Error Message: " . $e->getMessage() . "\n\n";
    
    echo "Stack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    
    exit(1);
}

echo "\n=== Test Complete ===\n";
