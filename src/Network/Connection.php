<?php

namespace XnoxsProto\Network;

class Connection
{
    private string $ip;
    private int $port;
    private TcpAbridged $transport;

    public function __construct(string $ip, int $port)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    public function connect(): void
    {
        $this->transport = new TcpAbridged($this->ip, $this->port);
    }

    public function send(string $data): void
    {
        $this->transport->send($data);
    }

    public function recv(): string
    {
        return $this->transport->recv();
    }

    public function close(): void
    {
        if ($this->transport) {
            $this->transport->close();
        }
    }

    public function isConnected(): bool
    {
        return $this->transport !== null;
    }

    public function getTransport(): TcpAbridged
    {
        return $this->transport;
    }
}
