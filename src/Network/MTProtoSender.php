<?php

namespace TelethonPHP\Network;

use TelethonPHP\Crypto\AES;
use TelethonPHP\Crypto\AuthKey;
use TelethonPHP\TL\BinaryWriter;
use TelethonPHP\TL\BinaryReader;
use TelethonPHP\Helpers\Helpers;

class MTProtoSender
{
    private Connection $connection;
    private AuthKey $authKey;
    private int $sessionId;
    private int $seqNo = 0;
    private int $msgId = 0;
    private int $salt = 0;
    private int $timeOffset = 0;

    public function __construct(Connection $connection, AuthKey $authKey, int $timeOffset = 0)
    {
        $this->connection = $connection;
        $this->authKey = $authKey;
        $this->sessionId = unpack('P', random_bytes(8))[1];
        $this->timeOffset = $timeOffset;
        $this->salt = unpack('P', random_bytes(8))[1];
    }

    public function send($request): array
    {
        $maxRetries = 5;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $msgId = $this->getNewMsgId();
            $seqNo = $this->getSeqNo(true);
            
            $bodyWriter = new BinaryWriter();
            $request->serialize($bodyWriter);
            $body = $bodyWriter->getValue();
            
            $writer = new BinaryWriter();
            $writer->writeLong($this->salt);
            $writer->writeLong($this->sessionId);
            $writer->writeLong($msgId);
            $writer->writeInt($seqNo);
            $writer->writeInt(strlen($body));
            $writer->write($body);
            
            $plaintext = $writer->getValue();
            
            $paddingLength = (16 - (strlen($plaintext) % 16)) % 16;
            if ($paddingLength < 12) {
                $paddingLength += 16;
            }
            $plaintext .= random_bytes($paddingLength);
            
            $msgKeyLarge = hash('sha256', substr($this->authKey->getKey(), 88, 32) . $plaintext, true);
            $msgKey = substr($msgKeyLarge, 8, 16);
            
            $encrypted = $this->aesCalculate($plaintext, $msgKey, true);
            
            $packet = new BinaryWriter();
            $packet->write($this->authKey->getKeyId());
            $packet->write($msgKey);
            $packet->write($encrypted);
            
            $this->connection->send($packet->getValue());
            
            $response = $this->connection->recv();
            
            $result = $this->processResponse($response);
            
            if ($result['constructor'] === 0xedab447b) {
                $badMsgId = $result['reader']->readLong();
                $badMsgSeqNo = $result['reader']->readInt();
                $errorCode = $result['reader']->readInt();
                $newServerSalt = $result['reader']->readLong();
                
                echo "[MTProto] ⚠️  bad_server_salt received (error $errorCode)\n";
                echo "[MTProto] Updating salt: " . dechex($this->salt) . " -> " . dechex($newServerSalt) . "\n";
                
                $this->salt = $newServerSalt;
                $attempt++;
                continue;
            }
            
            return $result;
        }
        
        throw new \RuntimeException('Max retries exceeded for bad_server_salt');
    }

    private function processResponse(string $response): array
    {
        $reader = new BinaryReader($response);
        
        $authKeyId = $reader->read(8);
        if ($authKeyId !== $this->authKey->getKeyId()) {
            throw new \RuntimeException('Invalid auth_key_id in response');
        }
        
        $msgKey = $reader->read(16);
        $encrypted = $reader->read(strlen($response) - 24);
        
        $plaintext = $this->aesCalculate($encrypted, $msgKey, false);
        
        $msgKeyCheck = substr(hash('sha256', substr($this->authKey->getKey(), 96, 32) . $plaintext, true), 8, 16);
        if ($msgKey !== $msgKeyCheck) {
            throw new \RuntimeException('msg_key mismatch - possible tampering detected');
        }
        
        $plaintextReader = new BinaryReader($plaintext);
        $salt = $plaintextReader->readLong();
        $sessionId = $plaintextReader->readLong();
        $msgId = $plaintextReader->readLong();
        $seqNo = $plaintextReader->readInt();
        $length = $plaintextReader->readInt();
        
        $constructor = $plaintextReader->readInt();
        
        if ($constructor === 0xf35c6d01) {
            $reqMsgId = $plaintextReader->readLong();
            $resultConstructor = $plaintextReader->readInt();
            
            if ($resultConstructor === 0x2144ca19) {
                $errorCode = $plaintextReader->readInt();
                $errorMessage = $plaintextReader->readString();
                
                echo "[MTProto] RPC Error $errorCode: $errorMessage\n";
                throw new \RuntimeException("RPC Error $errorCode: $errorMessage");
            }
            
            return [
                'constructor' => $resultConstructor,
                'reader' => $plaintextReader,
                'msg_id' => $msgId,
                'req_msg_id' => $reqMsgId,
                'length' => $length
            ];
        }
        
        if ($constructor === 0x73f1f8dc) {
            $containerSize = $plaintextReader->readInt();
            echo "[MTProto] msg_container with $containerSize messages\n";
            
            for ($i = 0; $i < $containerSize; $i++) {
                $innerMsgId = $plaintextReader->readLong();
                $innerSeqNo = $plaintextReader->readInt();
                $innerBytes = $plaintextReader->readInt();
                
                $innerConstructor = $plaintextReader->readInt();
                echo "[MTProto] Message $i: constructor=0x" . dechex($innerConstructor) . ", bytes=$innerBytes\n";
                
                if ($innerConstructor === 0xf35c6d01) {
                    $reqMsgId = $plaintextReader->readLong();
                    $resultConstructor = $plaintextReader->readInt();
                    
                    return [
                        'constructor' => $resultConstructor,
                        'reader' => $plaintextReader,
                        'msg_id' => $innerMsgId,
                        'req_msg_id' => $reqMsgId,
                        'length' => $innerBytes - 12
                    ];
                }
                
                if ($innerConstructor === 0x9ec20908) {
                    $firstMsgId = $plaintextReader->readLong();
                    $uniqueId = $plaintextReader->readLong();
                    $serverSalt = $plaintextReader->readLong();
                    
                    echo "[MTProto] new_session_created: updating salt to 0x" . dechex($serverSalt) . "\n";
                    $this->salt = $serverSalt;
                } else {
                    $plaintextReader->read($innerBytes - 4);
                }
            }
            
            echo "[MTProto] Container processed, receiving next packet...\n";
            $nextResponse = $this->connection->recv();
            return $this->processResponse($nextResponse);
        }
        
        return [
            'constructor' => $constructor,
            'reader' => $plaintextReader,
            'msg_id' => $msgId,
            'length' => $length
        ];
    }

    private function aesCalculate(string $data, string $msgKey, bool $encrypt): string
    {
        $x = $encrypt ? 0 : 8;
        
        $sha256a = hash('sha256', $msgKey . substr($this->authKey->getKey(), $x, 36), true);
        $sha256b = hash('sha256', substr($this->authKey->getKey(), 40 + $x, 36) . $msgKey, true);
        
        $aesKey = substr($sha256a, 0, 8) . substr($sha256b, 8, 16) . substr($sha256a, 24, 8);
        $aesIv = substr($sha256b, 0, 8) . substr($sha256a, 8, 16) . substr($sha256b, 24, 8);
        
        if ($encrypt) {
            return AES::encryptIGE($data, $aesKey, $aesIv);
        } else {
            return AES::decryptIGE($data, $aesKey, $aesIv);
        }
    }

    private function getNewMsgId(): int
    {
        $now = microtime(true) + $this->timeOffset;
        $nanoseconds = (int)(($now - floor($now)) * 1000000000);
        $seconds = (int)floor($now);
        
        $newMsgId = ($seconds << 32) | ($nanoseconds & 0xFFFFFFFC);
        
        if ($newMsgId <= $this->msgId) {
            $newMsgId = $this->msgId + 4;
        }
        
        $this->msgId = $newMsgId;
        return $newMsgId;
    }

    private function getSeqNo(bool $contentRelated): int
    {
        $seqNo = $this->seqNo * 2;
        if ($contentRelated) {
            $seqNo++;
            $this->seqNo++;
        }
        return $seqNo;
    }

    public function setTimeOffset(int $offset): void
    {
        $this->timeOffset = $offset;
    }
}
