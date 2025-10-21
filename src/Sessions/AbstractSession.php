<?php

namespace TelethonPHP\Sessions;

abstract class AbstractSession
{
    abstract public function setDC(int $dcId, string $serverAddress, int $port): void;
    
    abstract public function getDC(): ?array;
    
    abstract public function setAuthKey(?string $authKey): void;
    
    abstract public function getAuthKey(): ?string;
    
    abstract public function save(): void;
    
    abstract public function load(): void;
    
    abstract public function delete(): void;
    
    abstract public function setUpdateState(int $pts, int $qts, int $date, int $seq): void;
    
    abstract public function getUpdateState(): ?array;
    
    abstract public function processEntities(array $entities): void;
    
    abstract public function getEntityRowsByUsername(string $username): ?array;
    
    abstract public function getEntityRowsByPhone(string $phone): ?array;
    
    abstract public function getEntityRowsById(int $id): ?array;
}
