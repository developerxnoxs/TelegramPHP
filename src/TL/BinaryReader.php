<?php

namespace TelethonPHP\TL;

class BinaryReader
{
    private string $data;
    private int $position = 0;

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    public function read(int $length): string
    {
        if ($this->position + $length > strlen($this->data)) {
            throw new \RuntimeException('Not enough data to read');
        }
        
        $result = substr($this->data, $this->position, $length);
        $this->position += $length;
        
        return $result;
    }

    public function readInt(): int
    {
        return unpack('V', $this->read(4))[1];
    }

    public function readLong(): int
    {
        return unpack('q', $this->read(8))[1];
    }

    public function readDouble(): float
    {
        return unpack('d', $this->read(8))[1];
    }

    public function readBytes(): string
    {
        $firstByte = ord($this->read(1));
        
        if ($firstByte === 254) {
            $length = unpack('V', $this->read(3) . "\0")[1];
            $padding = $length % 4;
            if ($padding !== 0) {
                $padding = 4 - $padding;
            }
        } else {
            $length = $firstByte;
            $padding = ($length + 1) % 4;
            if ($padding !== 0) {
                $padding = 4 - $padding;
            }
        }
        
        $data = $this->read($length);
        $this->read($padding);
        
        return $data;
    }

    public function readString(): string
    {
        return $this->readBytes();
    }

    public function readBool(): bool
    {
        $value = $this->readInt();
        if ($value === 0x997275b5) {
            return true;
        } elseif ($value === 0xbc799737) {
            return false;
        }
        throw new \RuntimeException('Invalid bool value');
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function seek(int $position): void
    {
        $this->position = $position;
    }

    public function getRemainingData(): string
    {
        return substr($this->data, $this->position);
    }

    public function readObject()
    {
        $constructorId = $this->readInt();
        
        $typeMap = [
            0x05162463 => \TelethonPHP\TL\Types\ResPQ::class,
            0xd0e8075c => \TelethonPHP\TL\Types\ServerDHParamsOk::class,
            0x79cb045d => \TelethonPHP\TL\Types\ServerDHParamsFail::class,
            0xb5890dba => \TelethonPHP\TL\Types\ServerDHInnerData::class,
            0x3bcbf734 => \TelethonPHP\TL\Types\DhGenOk::class,
            0x46dc1fb9 => \TelethonPHP\TL\Types\DhGenRetry::class,
            0xa69dae02 => \TelethonPHP\TL\Types\DhGenFail::class,
        ];
        
        if (!isset($typeMap[$constructorId])) {
            throw new \RuntimeException(sprintf(
                'Unknown constructor ID: 0x%08x',
                $constructorId
            ));
        }
        
        $className = $typeMap[$constructorId];
        return $className::fromReader($this);
    }
}
