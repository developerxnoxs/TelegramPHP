<?php

namespace TelethonPHP\TL\Types;

use TelethonPHP\TL\BinaryReader;

class User
{
    const CONSTRUCTOR_ID = 0x83314861;
    
    public int $flags;
    public int $flags2 = 0;
    public int $id;
    public ?int $accessHash = null;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $username = null;
    public ?string $phone = null;
    public bool $self = false;
    public bool $contact = false;
    public bool $bot = false;
    public bool $verified = false;
    public bool $premium = false;

    public static function fromReader(BinaryReader $reader): self
    {
        $obj = new self();
        
        $obj->flags = $reader->readInt();
        
        $obj->self = (bool)($obj->flags & (1 << 10));
        $obj->contact = (bool)($obj->flags & (1 << 11));
        $obj->bot = (bool)($obj->flags & (1 << 14));
        $obj->verified = (bool)($obj->flags & (1 << 17));
        $obj->premium = (bool)($obj->flags & (1 << 28));
        
        $obj->flags2 = $reader->readInt();
        
        $obj->id = $reader->readLong();
        
        if ($obj->flags & (1 << 0)) {
            $obj->accessHash = $reader->readLong();
        }
        
        if ($obj->flags & (1 << 1)) {
            $obj->firstName = $reader->readString();
        }
        
        if ($obj->flags & (1 << 2)) {
            $obj->lastName = $reader->readString();
        }
        
        if ($obj->flags & (1 << 3)) {
            $obj->username = $reader->readString();
        }
        
        if ($obj->flags & (1 << 4)) {
            $obj->phone = $reader->readString();
        }
        
        return $obj;
    }
    
    public function getFullName(): string
    {
        $name = $this->firstName ?? '';
        if ($this->lastName) {
            $name .= ' ' . $this->lastName;
        }
        return trim($name);
    }
}
