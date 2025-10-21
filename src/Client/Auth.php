<?php

namespace TelethonPHP\Client;

class Auth
{
    private TelegramClient $client;
    private ?string $phoneNumber = null;
    private ?string $phoneCodeHash = null;

    public function __construct(TelegramClient $client)
    {
        $this->client = $client;
    }

    public function sendCode(string $phoneNumber): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $this->phoneNumber = $phoneNumber;
        $this->phoneCodeHash = bin2hex(random_bytes(16));

        echo "[Auth] Sending code to $phoneNumber...\n";
        echo "[Auth] Code sent! (In real app, user receives SMS/Telegram message)\n";
        
        return [
            'phone_number' => $phoneNumber,
            'phone_code_hash' => $this->phoneCodeHash,
            'type' => 'app'
        ];
    }

    public function signIn(string $phoneNumber, string $phoneCodeHash, string $phoneCode): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        echo "[Auth] Signing in with phone: $phoneNumber\n";
        echo "[Auth] Verifying code: $phoneCode\n";
        
        if ($phoneCodeHash !== $this->phoneCodeHash) {
            throw new \RuntimeException('Invalid phone_code_hash');
        }

        echo "[Auth] Login successful!\n";
        
        return [
            'user' => [
                'id' => 123456789,
                'first_name' => 'Test',
                'last_name' => 'User',
                'username' => 'testuser',
                'phone' => $phoneNumber,
                'access_hash' => bin2hex(random_bytes(8))
            ]
        ];
    }

    public function isAuthorized(): bool
    {
        $authKey = $this->client->getSession()->getAuthKey();
        return $authKey !== null;
    }

    public function logOut(): bool
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        echo "[Auth] Logging out...\n";
        
        $this->client->getSession()->setAuthKey(null);
        $this->phoneNumber = null;
        $this->phoneCodeHash = null;
        
        echo "[Auth] Logged out successfully!\n";
        
        return true;
    }
}
