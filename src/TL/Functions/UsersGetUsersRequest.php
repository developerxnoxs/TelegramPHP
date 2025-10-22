<?php

namespace TelethonPHP\TL\Functions;

use TelethonPHP\TL\TLObject;
use TelethonPHP\TL\BinaryWriter;

class UsersGetUsersRequest extends TLObject
{
    public const CONSTRUCTOR_ID = 0x0d91a548;
    
    private array $id;

    public function __construct(array $id = [])
    {
        $this->id = $id;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR_ID);
        
        $writer->writeInt(0x1cb5c415);
        $writer->writeInt(count($this->id));
        
        foreach ($this->id as $userId) {
            $writer->writeInt(0xf21158c6);
            $writer->writeLong($userId);
        }
    }

    public function toDict(): array
    {
        return [
            '_' => 'users.getUsers',
            'id' => $this->id
        ];
    }

    public function toBytes(): string
    {
        $writer = new BinaryWriter();
        $this->serialize($writer);
        return $writer->getValue();
    }
}
