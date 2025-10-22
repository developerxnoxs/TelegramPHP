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
        
        $request = new AuthSendCodeRequest(
            $phoneNumber,
            $this->client->getApiId(),
            $this->client->getApiHash()
        );

        if ($this->client->isFirstRequest()) {
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
            
            return [
                'phone_number' => $phoneNumber,
                'phone_code_hash' => $sentCode->phoneCodeHash,
                'type' => $sentCode->type
            ];
        } catch (\TelethonPHP\Exceptions\RPCException $e) {
            if ($e->errorCode === 303) {
                if (preg_match('/(PHONE|USER|NETWORK)_MIGRATE_(\d+)/', $e->errorMessage, $matches)) {
                    $newDc = (int)$matches[2];
                    
                    $this->client->connect($newDc, true);
                    
                    return $this->sendCode($phoneNumber);
                }
            }
            
            throw $e;
        } catch (\Exception $e) {
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
        
        $this->client->getSession()->setAuthKey(null);
        $this->phoneNumber = null;
        $this->phoneCodeHash = null;
        $this->authorized = false;
        
        return true;
    }
}
