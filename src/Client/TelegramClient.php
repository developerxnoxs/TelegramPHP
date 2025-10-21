<?php

namespace TelethonPHP\Client;

use TelethonPHP\Sessions\AbstractSession;
use TelethonPHP\Sessions\MemorySession;
use TelethonPHP\Network\Connection;
use TelethonPHP\Network\Authenticator;
use TelethonPHP\Network\MTProtoSender;
use TelethonPHP\Crypto\RSA;
use TelethonPHP\Crypto\AuthKey;

class TelegramClient
{
    private int $apiId;
    private string $apiHash;
    private AbstractSession $session;
    private ?Connection $connection = null;
    private ?Auth $auth = null;
    private ?MTProtoSender $sender = null;
    private int $timeOffset = 0;
    
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

    public function connect(int $dcId = 2): void
    {
        if (!isset(self::DC_OPTIONS[$dcId])) {
            throw new \InvalidArgumentException("Invalid DC ID: $dcId");
        }
        
        $dc = self::DC_OPTIONS[$dcId];
        $this->connection = new Connection($dc['ip'], $dc['port']);
        $this->connection->connect();
        
        $this->session->setDC($dcId, $dc['ip'], $dc['port']);
        
        echo "[Client] Connected to DC $dcId ({$dc['ip']}:{$dc['port']})\n";
        
        if ($this->session->getAuthKey() === null) {
            echo "[Client] No auth key found, generating new one...\n";
            $authenticator = new Authenticator($this->connection);
            $authKey = $authenticator->doAuthentication();
            $this->session->setAuthKey($authKey->getKey());
            $this->timeOffset = $authenticator->getTimeOffset();
            echo "[Client] Auth key generated and saved!\n";
        } else {
            echo "[Client] Using existing auth key\n";
        }
        
        $authKeyObj = new AuthKey($this->session->getAuthKey());
        $this->sender = new MTProtoSender($this->connection, $authKeyObj, $this->timeOffset);
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

            echo "\n[Client] Starting authentication flow...\n";
            
            $sentCode = $this->auth->sendCode($phone);
            
            if ($codeCallback !== null) {
                $code = $codeCallback();
            } else {
                echo "[Client] Enter the code you received: ";
                $code = trim(fgets(STDIN));
            }
            
            $user = $this->auth->signIn($phone, $sentCode['phone_code_hash'], $code);
            
            $firstName = $user['user']['first_name'] ?? 'User';
            echo "[Client] Welcome, {$firstName}!\n";
        } else {
            echo "[Client] Already authorized\n";
        }
    }

    public function disconnect(): void
    {
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
            echo "[Client] Disconnected from Telegram\n";
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
        
        echo "[Client] Sending message to $chatId: $text\n";
    }

    public function getMe(): array
    {
        if (!$this->isConnected()) {
            throw new \RuntimeException('Not connected to Telegram');
        }
        
        if (!$this->auth->isAuthorized()) {
            throw new \RuntimeException('Not authorized');
        }
        
        echo "[Client] Getting current user info...\n";
        
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
}
