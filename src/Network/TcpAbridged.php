<?php

namespace TelethonPHP\Network;

class TcpAbridged
{
    private $socket;
    private bool $initialized = false;

    public function __construct(string $ip, int $port)
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $this->socket = stream_socket_client(
            "tcp://{$ip}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$this->socket) {
            throw new \RuntimeException("Failed to connect: $errstr ($errno)");
        }
        
        stream_set_timeout($this->socket, 10);
        stream_set_blocking($this->socket, true);
        
        fwrite($this->socket, "\xef");
        
        $this->initialized = true;
    }

    public function send(string $data): void
    {
        $length = strlen($data) >> 2;
        
        if ($length < 127) {
            $packet = pack('C', $length) . $data;
        } else {
            $packet = "\x7f" . substr(pack('V', $length), 0, 3) . $data;
        }
        
        $written = fwrite($this->socket, $packet);
        if ($written === false || $written !== strlen($packet)) {
            throw new \RuntimeException('Failed to send packet');
        }
    }

    public function recv(): string
    {
        $lengthByte = fread($this->socket, 1);
        if ($lengthByte === false || $lengthByte === '') {
            throw new \RuntimeException('Failed to read length byte');
        }
        
        $length = ord($lengthByte);
        
        if ($length >= 127) {
            $lengthBytes = fread($this->socket, 3);
            if (strlen($lengthBytes) !== 3) {
                throw new \RuntimeException('Failed to read extended length');
            }
            $length = unpack('V', $lengthBytes . "\x00")[1];
        }
        
        $dataLength = $length << 2;
        
        $data = '';
        while (strlen($data) < $dataLength) {
            $chunk = fread($this->socket, $dataLength - strlen($data));
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('Failed to read packet data');
            }
            $data .= $chunk;
        }
        
        return $data;
    }

    public function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
        }
    }

    public function isConnected(): bool
    {
        return $this->socket && !feof($this->socket);
    }
}
