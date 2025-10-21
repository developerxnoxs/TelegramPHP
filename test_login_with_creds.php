<?php

require_once __DIR__ . '/vendor/autoload.php';

use TelethonPHP\Client\TelegramClient;
use TelethonPHP\Sessions\FileSession;

echo "=== TelethonPHP - Real Telegram Login Test ===\n\n";

$apiId = 26778854;
$apiHash = '910201ff8e628ce461dbac3c7723feda';
$phone = '+6283144900354';

echo "API ID: $apiId\n";
echo "Phone: $phone\n\n";

echo str_repeat("=", 60) . "\n";
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
    echo "  First Name: " . ($user['user']['first_name'] ?? 'N/A') . "\n";
    if ($user['user']['last_name']) {
        echo "  Last Name: " . $user['user']['last_name'] . "\n";
    }
    if ($user['user']['username']) {
        echo "  Username: @" . $user['user']['username'] . "\n";
    }
    
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
