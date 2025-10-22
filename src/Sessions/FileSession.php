<?php

namespace XnoxsProto\Sessions;

class FileSession extends AbstractSession
{
    private string $filename;
    private ?int $dcId = null;
    private ?string $serverAddress = null;
    private ?int $port = null;
    private ?string $authKey = null;
    private array $updateState = [];
    private array $entities = [];

    public function __construct(string $filename)
    {
        $this->filename = $filename;
        $this->load();
    }

    public function setDC(int $dcId, string $serverAddress, int $port): void
    {
        $this->dcId = $dcId;
        $this->serverAddress = $serverAddress;
        $this->port = $port;
        $this->save();
    }

    public function getDC(): ?array
    {
        if ($this->dcId === null) {
            return null;
        }
        
        return [
            'dc_id' => $this->dcId,
            'server_address' => $this->serverAddress,
            'port' => $this->port
        ];
    }

    public function setAuthKey(?string $authKey): void
    {
        $this->authKey = $authKey;
        $this->save();
    }

    public function getAuthKey(): ?string
    {
        return $this->authKey;
    }

    public function save(): void
    {
        $data = [
            'dc_id' => $this->dcId,
            'server_address' => $this->serverAddress,
            'port' => $this->port,
            'auth_key' => $this->authKey ? base64_encode($this->authKey) : null,
            'update_state' => $this->updateState,
            'entities' => $this->entities
        ];
        
        file_put_contents($this->filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function load(): void
    {
        if (!file_exists($this->filename)) {
            return;
        }
        
        $content = file_get_contents($this->filename);
        if (!$content) {
            return;
        }
        
        $data = json_decode($content, true);
        if (!$data) {
            return;
        }
        
        $this->dcId = $data['dc_id'] ?? null;
        $this->serverAddress = $data['server_address'] ?? null;
        $this->port = $data['port'] ?? null;
        $this->authKey = isset($data['auth_key']) ? base64_decode($data['auth_key']) : null;
        $this->updateState = $data['update_state'] ?? [];
        $this->entities = $data['entities'] ?? [];
    }

    public function delete(): void
    {
        if (file_exists($this->filename)) {
            unlink($this->filename);
        }
        
        $this->dcId = null;
        $this->serverAddress = null;
        $this->port = null;
        $this->authKey = null;
        $this->updateState = [];
        $this->entities = [];
    }

    public function setUpdateState(int $pts, int $qts, int $date, int $seq): void
    {
        $this->updateState = [
            'pts' => $pts,
            'qts' => $qts,
            'date' => $date,
            'seq' => $seq
        ];
        $this->save();
    }

    public function getUpdateState(): ?array
    {
        return empty($this->updateState) ? null : $this->updateState;
    }

    public function processEntities(array $entities): void
    {
        foreach ($entities as $entity) {
            if (isset($entity['id'])) {
                $this->entities[$entity['id']] = $entity;
            }
        }
        $this->save();
    }

    public function getEntityRowsByUsername(string $username): ?array
    {
        foreach ($this->entities as $entity) {
            if (isset($entity['username']) && $entity['username'] === $username) {
                return $entity;
            }
        }
        return null;
    }

    public function getEntityRowsByPhone(string $phone): ?array
    {
        foreach ($this->entities as $entity) {
            if (isset($entity['phone']) && $entity['phone'] === $phone) {
                return $entity;
            }
        }
        return null;
    }

    public function getEntityRowsById(int $id): ?array
    {
        return $this->entities[$id] ?? null;
    }
}
