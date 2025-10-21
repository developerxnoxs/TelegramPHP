<?php

namespace TelethonPHP\TL\Functions;

use TelethonPHP\TL\TLObject;
use TelethonPHP\TL\BinaryWriter;

class InvokeWithLayerRequest extends TLObject
{
    public const CONSTRUCTOR_ID = 0xda9b0d0d;
    
    private int $layer;
    private $query;

    public function __construct(int $layer, $query)
    {
        $this->layer = $layer;
        $this->query = $query;
    }

    public function serialize(BinaryWriter $writer): void
    {
        $writer->writeInt(self::CONSTRUCTOR_ID);
        $writer->writeInt($this->layer);
        $this->query->serialize($writer);
    }
}
