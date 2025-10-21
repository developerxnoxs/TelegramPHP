<?php

namespace TelethonPHP\Client;

use TelethonPHP\TL\Functions\AuthSendCodeRequest;
use TelethonPHP\TL\Functions\AuthSignInRequest;
use TelethonPHP\TL\Functions\InvokeWithLayerRequest;
use TelethonPHP\TL\Functions\InitConnectionRequest;
use TelethonPHP\TL\Types\AuthSentCode;
use TelethonPHP\TL\Types\AuthAuthorization;
use TelethonPHP\TL\BinaryReader;

class Auth
{
    private TelegramClient $client;
    private ?string $phoneNumber = null;
    private ?string $phoneCodeHash = null;
    private bool $authorized = false;

    public function __construct(TelegramClient $client)
    {
        $this->client = $client;
    }

    public function sendCode(string $phoneNumber): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $sender = $this->client->getSender();
        if (!$sender) {
            throw new \RuntimeException('MTProto sender not initialized');
        }

        $this->phoneNumber = $phoneNumber;

        echo "[Auth] Sending code to $phoneNumber...\n";
        
        $request = new AuthSendCodeRequest(
            $phoneNumber,
            $this->client->getApiId(),
            $this->client->getApiHash()
        );

        if ($this->client->isFirstRequest()) {
            echo "[Auth] Wrapping with initConnection + invokeWithLayer (first request)\n";
            
            $request = new InvokeWithLayerRequest(
                214,
                new InitConnectionRequest(
                    $this->client->getApiId(),
                    'TelethonPHP',
                    php_uname('s') . ' ' . php_uname('r'),
                    '1.0.0',
                    'en',
                    '',
                    'en',
                    $request
                )
            );
            
            $this->client->markFirstRequestSent();
        }

        try {
            $response = $sender->send($request);
            
            if ($response['constructor'] !== AuthSentCode::CONSTRUCTOR_ID) {
                throw new \RuntimeException(sprintf(
                    'Unexpected constructor: 0x%08x, expected auth.sentCode',
                    $response['constructor']
                ));
            }
            
            $sentCode = AuthSentCode::fromReader($response['reader']);
            
            $this->phoneCodeHash = $sentCode->phoneCodeHash;

            echo "[Auth] ✅ Code sent! Check your Telegram app or SMS\n";
            echo "[Auth] Code type: 0x" . dechex($sentCode->type['_constructor']) . "\n";
            if ($sentCode->timeout) {
                echo "[Auth] Timeout: {$sentCode->timeout} seconds\n";
            }
            
            return [
                'phone_number' => $phoneNumber,
                'phone_code_hash' => $sentCode->phoneCodeHash,
                'type' => $sentCode->type
            ];
        } catch (\RuntimeException $e) {
            if (isset($e->errorCode) && $e->errorCode === 303) {
                if (preg_match('/(PHONE|USER|NETWORK)_MIGRATE_(\d+)/', $e->errorMessage, $matches)) {
                    $newDc = (int)$matches[2];
                    echo "[Auth] 🔄 DC Migration required: switching from current DC to DC $newDc\n";
                    
                    $this->client->connect($newDc, true);
                    
                    echo "[Auth] Retrying sendCode to $phoneNumber on DC $newDc...\n";
                    return $this->sendCode($phoneNumber);
                }
            }
            
            echo "[Auth] ❌ Error sending code: " . $e->getMessage() . "\n";
            throw $e;
        } catch (\Exception $e) {
            echo "[Auth] ❌ Error sending code: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function signIn(string $phoneNumber, string $phoneCodeHash, string $phoneCode): array
    {
        if (!$this->client->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }

        $sender = $this->client->getSender();
        if (!$sender) {
            throw new \RuntimeException('MTProto sender not initialized');
        }

        echo "[Auth] Signing in with phone: $phoneNumber\n";
        echo "[Auth] Verifying code: $phoneCode\n";
        
        $request = new AuthSignInRequest(
            $phoneNumber,
            $phoneCodeHash,
            $phoneCode
        );

        try {
            $response = $sender->send($request);
            
            if ($response['constructor'] !== AuthAuthorization::CONSTRUCTOR_ID) {
                throw new \RuntimeException(sprintf(
                    'Unexpected constructor: 0x%08x, expected auth.authorization',
                    $response['constructor']
                ));
            }
            
            $authorization = AuthAuthorization::fromReader($response['reader']);
            
            $this->authorized = true;

            echo "[Auth] ✅ Login successful!\n";
            echo "[Auth] User ID: {$authorization->user->id}\n";
            echo "[Auth] Name: " . $authorization->user->getFullName() . "\n";
            if ($authorization->user->username) {
                echo "[Auth] Username: @{$authorization->user->username}\n";
            }
            
            return [
                'user' => [
                    'id' => $authorization->user->id,
                    'first_name' => $authorization->user->firstName,
                    'last_name' => $authorization->user->lastName,
                    'username' => $authorization->user->username,
                    'phone' => $authorization->user->phone,
                    'authorized' => true
                ]
            ];
        } catch (\Exception $e) {
            echo "[Auth] ❌ Error signing in: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    public function isAuthorized(): bool
    {
        if ($this->authorized) {
            return true;
        }
        
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
        $this->authorized = false;
        
        echo "[Auth] Logged out successfully!\n";
        
        return true;
    }
}
