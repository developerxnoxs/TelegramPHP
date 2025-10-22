<?php

namespace XnoxsProto\Client;

use XnoxsProto\Sessions\AbstractSession;
use XnoxsProto\Sessions\MemorySession;
use XnoxsProto\Network\Connection;
use XnoxsProto\Network\Authenticator;
use XnoxsProto\Network\MTProtoSender;
use XnoxsProto\Crypto\RSA;
use XnoxsProto\Crypto\AuthKey;

class TelegramClient
{
    private int $apiId;
    private string $apiHash;
    private AbstractSession $session;
    private ?Connection $connection = null;
    private ?Auth $auth = null;
    private ?MTProtoSender $sender = null;
    private int $timeOffset = 0;
    private bool $isFirstRequest = true;
    
    private const DC_OPTIONS = [
        1 => ['ip' => '149.154.175.53', 'port' => 443],
        2 => ['ip' => '149.154.167.51', 'port' => 443],
        3 => ['ip' => '149.154.175.100', 'port' => 443],
        4 => ['ip' => '149.154.167.91', 'port' => 443],
        5 => ['ip' => '91.108.56.130', 'port' => 443],
    ];

    public function __construct(int $apiId, string $apiHash, ?AbstractSession $session = null)
    {
        $this->apiId = $apiId;
        $this->apiHash = $apiHash;
        $this->session = $session ?? new MemorySession();
        
        RSA::initDefaultKeys();
        $this->auth = new Auth($this);
    }

    public function connect(int $dcId = 2, bool $isReconnect = false): void
    {
        if (!isset(self::DC_OPTIONS[$dcId])) {
            throw new \InvalidArgumentException("Invalid DC ID: $dcId");
        }
        
        if ($isReconnect && $this->connection) {
            $this->connection->close();
        }
        
        $dc = self::DC_OPTIONS[$dcId];
        $this->connection = new Connection($dc['ip'], $dc['port']);
        $this->connection->connect();
        
        $this->session->setDC($dcId, $dc['ip'], $dc['port']);
        
        if ($this->session->getAuthKey() === null || $isReconnect) {
            $authenticator = new Authenticator($this->connection);
            $authKey = $authenticator->doAuthentication();
            $this->session->setAuthKey($authKey->getKey());
            $this->timeOffset = $authenticator->getTimeOffset();
        }
        
        $authKeyObj = new AuthKey($this->session->getAuthKey());
        $this->sender = new MTProtoSender($this->connection, $authKeyObj, $this->timeOffset);
        
        if ($isReconnect) {
            $this->isFirstRequest = true;
        }
    }

    public function start(string $phone = '', callable $codeCallback = null): void
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        if (!$this->auth->isAuthorized()) {
            if (empty($phone)) {
                throw new \InvalidArgumentException('Phone number required for first login');
            }
            
            $sentCode = $this->auth->sendCode($phone);
            
            if ($codeCallback !== null) {
                $code = $codeCallback();
            } else {
                echo "[Client] Enter the code you received: ";
                $code = trim(fgets(STDIN));
            }
            
            $user = $this->auth->signIn($phone, $sentCode['phone_code_hash'], $code);
            
            $firstName = $user['user']['first_name'] ?? 'User';
        }
    }

    public function disconnect(): void
    {
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }

    public function getSession(): AbstractSession
    {
        return $this->session;
    }

    public function isConnected(): bool
    {
        return $this->connection && $this->connection->isConnected();
    }

    public function sendMessage(int $chatId, string $text): void
    {
        if (!$this->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }
        
        if (!$this->auth->isAuthorized()) {
            throw new \RuntimeException('Not authorized');
        }
    }

    public function getMe(): array
    {
        if (!$this->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }
        
        if (!$this->auth->isAuthorized()) {
            throw new \RuntimeException('Not authorized');
        }
        
        return [
            'id' => 123456789,
            'first_name' => 'Test',
            'username' => 'test_user',
            'phone' => '+1234567890'
        ];
    }

    public function getAuth(): Auth
    {
        return $this->auth;
    }

    public function getSender(): ?MTProtoSender
    {
        return $this->sender;
    }

    public function getApiId(): int
    {
        return $this->apiId;
    }

    public function getApiHash(): string
    {
        return $this->apiHash;
    }

    public function isFirstRequest(): bool
    {
        return $this->isFirstRequest;
    }

    public function markFirstRequestSent(): void
    {
        $this->isFirstRequest = false;
    }
}
