<?php

namespace TelethonPHP\TL\Functions;

use TelethonPHP\TL\TLObject;
use TelethonPHP\TL\BinaryWriter;

class AuthSignInRequest extends TLObject
{
    const CONSTRUCTOR = 0x8d52a951;
    
    private string $phoneNumber;
    private string $phoneCodeHash;
    private string $phoneCode;

    public function __construct(string $phoneNumber, string $phoneCodeHash, string $phoneCode)
    {
        $this->phoneNumber = $phoneNumber;
        $this->phoneCodeHash = $phoneCodeHash;
        $this->phoneCode = $phoneCode;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR);
        
        $flags = 0;
        $writer->writeInt($flags);
        
        $writer->writeString($this->phoneNumber);
        $writer->writeString($this->phoneCodeHash);
        $writer->writeString($this->phoneCode);
    }

    public function getConstructor(): int
    {
        return self::CONSTRUCTOR;
    }

    public function toDict(): array
    {
        return [
            '_' => 'auth.signIn',
            'phone_number' => $this->phoneNumber,
            'phone_code_hash' => $this->phoneCodeHash,
            'phone_code' => $this->phoneCode
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
