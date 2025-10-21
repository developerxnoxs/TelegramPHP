<?php

namespace TelethonPHP\Network;

use TelethonPHP\Crypto\RSA;
use TelethonPHP\Crypto\AES;
use TelethonPHP\Crypto\AuthKey;
use TelethonPHP\Helpers\Helpers;
use TelethonPHP\TL\BinaryReader;
use TelethonPHP\TL\Types\ResPQ;
use TelethonPHP\TL\Types\PQInnerData;
use TelethonPHP\TL\Types\ServerDHParamsOk;
use TelethonPHP\TL\Types\ServerDHParamsFail;
use TelethonPHP\TL\Types\ServerDHInnerData;
use TelethonPHP\TL\Types\ClientDHInnerData;
use TelethonPHP\TL\Types\DhGenOk;
use TelethonPHP\TL\Types\DhGenRetry;
use TelethonPHP\TL\Types\DhGenFail;
use TelethonPHP\TL\Functions\ReqPqMultiRequest;
use TelethonPHP\TL\Functions\ReqDHParamsRequest;
use TelethonPHP\TL\Functions\SetClientDHParamsRequest;

class Authenticator
{
    private MTProtoPlainSender $sender;

    public function __construct(string $ip, int $port)
    {
        $connection = new TcpAbridged($ip, $port);
        $this->sender = new MTProtoPlainSender($connection);
        RSA::initDefaultKeys();
    }

    public function doAuthentication(): AuthKey
    {
        echo "[Auth] Starting MTProto authentication...\n\n";
        
        // Step 1: Send req_pq_multi
        $nonce = Helpers::generateRandomBytes(16);
        echo "[Auth] Step 1: Sending req_pq_multi\n";
        echo "[Auth] nonce: " . bin2hex($nonce) . "\n";
        
        $reqPq = new ReqPqMultiRequest($nonce);
        $reqPqBytes = $reqPq->toBytes();
        
        $response = $this->sender->sendRecv($reqPqBytes);
        $reader = new BinaryReader($response['data']);
        
        $constructorId = $reader->readInt();
        if ($constructorId !== ResPQ::CONSTRUCTOR_ID) {
            throw new \RuntimeException(sprintf(
                'Expected ResPQ constructor 0x%08x, got 0x%08x',
                ResPQ::CONSTRUCTOR_ID,
                $constructorId
            ));
        }
        
        $resPq = ResPQ::fromReader($reader);
        echo "[Auth] ✅ Received ResPQ from Telegram!\n\n";
        
        // Step 2: Validate nonces
        if ($resPq->nonce !== $nonce) {
            throw new \RuntimeException('Nonce mismatch in ResPQ');
        }
        echo "[Auth] ✅ Nonce validated\n";
        echo "[Auth] server_nonce: " . bin2hex($resPq->serverNonce) . "\n\n";
        
        // Step 3: Factorize PQ
        $pqInt = Helpers::getInt($resPq->pq);
        echo "[Auth] Step 2: Factorizing PQ\n";
        echo "[Auth] PQ = " . gmp_strval($pqInt) . "\n";
        
        [$p, $q] = Helpers::factorize($pqInt);
        echo "[Auth] ✅ Factorized: p=$p, q=$q\n\n";
        
        $pBytes = Helpers::getByteArray(gmp_init($p));
        $qBytes = Helpers::getByteArray(gmp_init($q));
        
        // Step 4: Prepare p_q_inner_data and encrypt with RSA
        $newNonce = Helpers::generateRandomBytes(32);
        echo "[Auth] Step 3: Preparing p_q_inner_data\n";
        echo "[Auth] new_nonce: " . bin2hex(substr($newNonce, 0, 16)) . "...\n";
        
        $pqInnerData = new PQInnerData(
            $resPq->pq,
            $pBytes,
            $qBytes,
            $resPq->nonce,
            $resPq->serverNonce,
            $newNonce
        );
        
        $pqInnerBytes = $pqInnerData->toBytes();
        
        echo "[Auth] Encrypting with RSA...\n";
        $cipherText = null;
        $targetFingerprint = null;
        
        foreach ($resPq->serverPublicKeyFingerprints as $fingerprint) {
            $cipherText = RSA::encrypt($fingerprint, $pqInnerBytes, false);
            if ($cipherText !== null) {
                $targetFingerprint = $fingerprint;
                echo "[Auth] ✅ Found matching key: " . sprintf('0x%016x', $fingerprint) . "\n\n";
                break;
            }
        }
        
        if ($cipherText === null) {
            foreach ($resPq->serverPublicKeyFingerprints as $fingerprint) {
                $cipherText = RSA::encrypt($fingerprint, $pqInnerBytes, true);
                if ($cipherText !== null) {
                    $targetFingerprint = $fingerprint;
                    echo "[Auth] ✅ Found matching OLD key: " . sprintf('0x%016x', $fingerprint) . "\n\n";
                    break;
                }
            }
        }
        
        if ($cipherText === null) {
            throw new \RuntimeException('No matching RSA key found');
        }
        
        // Step 5: Send req_DH_params
        echo "[Auth] Step 4: Sending req_DH_params\n";
        $reqDHParams = new ReqDHParamsRequest(
            $resPq->nonce,
            $resPq->serverNonce,
            $pBytes,
            $qBytes,
            $targetFingerprint,
            $cipherText
        );
        
        $response = $this->sender->sendRecv($reqDHParams->toBytes());
        $reader = new BinaryReader($response['data']);
        $serverDHParams = $reader->readObject();
        
        if ($serverDHParams instanceof ServerDHParamsFail) {
            throw new \RuntimeException('Server returned DH params fail');
        }
        
        if (!($serverDHParams instanceof ServerDHParamsOk)) {
            throw new \RuntimeException('Expected ServerDHParamsOk');
        }
        
        echo "[Auth] ✅ Received ServerDHParamsOk\n\n";
        
        if ($serverDHParams->nonce !== $resPq->nonce) {
            throw new \RuntimeException('Nonce mismatch in ServerDHParams');
        }
        
        if ($serverDHParams->serverNonce !== $resPq->serverNonce) {
            throw new \RuntimeException('Server nonce mismatch in ServerDHParams');
        }
        
        // Step 6: Decrypt server_DH_inner_data
        echo "[Auth] Step 5: Decrypting server_DH_inner_data\n";
        [$key, $iv] = Helpers::generateKeyDataFromNonce($resPq->serverNonce, $newNonce);
        
        if (strlen($serverDHParams->encryptedAnswer) % 16 !== 0) {
            throw new \RuntimeException('Invalid encrypted answer length');
        }
        
        $plainText = AES::decryptIGE($serverDHParams->encryptedAnswer, $key, $iv);
        
        $reader = new BinaryReader($plainText);
        $hashSum = $reader->read(20);
        $serverDHInner = $reader->readObject();
        
        if (!($serverDHInner instanceof ServerDHInnerData)) {
            throw new \RuntimeException('Expected ServerDHInnerData');
        }
        
        echo "[Auth] ✅ Decrypted and parsed ServerDHInnerData\n\n";
        
        if ($serverDHInner->nonce !== $resPq->nonce) {
            throw new \RuntimeException('Nonce mismatch in ServerDHInnerData');
        }
        
        if ($serverDHInner->serverNonce !== $resPq->serverNonce) {
            throw new \RuntimeException('Server nonce mismatch in ServerDHInnerData');
        }
        
        // Step 7: Complete DH exchange
        echo "[Auth] Step 6: Computing DH key exchange\n";
        $dhPrime = Helpers::getInt($serverDHInner->dhPrime, false);
        $g = gmp_init($serverDHInner->g);
        $gA = Helpers::getInt($serverDHInner->gA, false);
        $timeOffset = $serverDHInner->serverTime - time();
        
        echo "[Auth] g = " . gmp_strval($g) . "\n";
        echo "[Auth] time_offset = " . $timeOffset . " seconds\n";
        
        // Generate random b (256 bytes)
        $b = Helpers::getInt(Helpers::generateRandomBytes(256), false);
        $gB = gmp_powm($g, $b, $dhPrime);
        $gab = gmp_powm($gA, $b, $dhPrime);
        
        // Security checks
        $one = gmp_init(1);
        $dhPrimeMinusOne = gmp_sub($dhPrime, $one);
        
        if (gmp_cmp($g, $one) <= 0 || gmp_cmp($g, $dhPrimeMinusOne) >= 0) {
            throw new \RuntimeException('g is not within (1, dh_prime - 1)');
        }
        
        if (gmp_cmp($gA, $one) <= 0 || gmp_cmp($gA, $dhPrimeMinusOne) >= 0) {
            throw new \RuntimeException('g_a is not within (1, dh_prime - 1)');
        }
        
        if (gmp_cmp($gB, $one) <= 0 || gmp_cmp($gB, $dhPrimeMinusOne) >= 0) {
            throw new \RuntimeException('g_b is not within (1, dh_prime - 1)');
        }
        
        $safetyRange = gmp_pow(2, 2048 - 64);
        if (gmp_cmp($gA, $safetyRange) < 0 || gmp_cmp($gA, gmp_sub($dhPrime, $safetyRange)) > 0) {
            throw new \RuntimeException('g_a is not within safety range');
        }
        
        if (gmp_cmp($gB, $safetyRange) < 0 || gmp_cmp($gB, gmp_sub($dhPrime, $safetyRange)) > 0) {
            throw new \RuntimeException('g_b is not within safety range');
        }
        
        echo "[Auth] ✅ DH security checks passed\n\n";
        
        // Step 8: Send set_client_DH_params
        echo "[Auth] Step 7: Sending set_client_DH_params\n";
        $clientDHInner = new ClientDHInnerData(
            $resPq->nonce,
            $resPq->serverNonce,
            0,
            Helpers::getByteArray($gB)
        );
        
        $clientDHInnerBytes = $clientDHInner->toBytes();
        $clientDHInnerHashed = sha1($clientDHInnerBytes, true) . $clientDHInnerBytes;
        
        $clientDHEncrypted = AES::encryptIGE($clientDHInnerHashed, $key, $iv);
        
        $setClientDH = new SetClientDHParamsRequest(
            $resPq->nonce,
            $resPq->serverNonce,
            $clientDHEncrypted
        );
        
        $response = $this->sender->sendRecv($setClientDH->toBytes());
        $reader = new BinaryReader($response['data']);
        $dhGen = $reader->readObject();
        
        echo "[Auth] ✅ Received DH generation result\n\n";
        
        // Step 9: Validate result
        if ($dhGen->nonce !== $resPq->nonce) {
            throw new \RuntimeException('Nonce mismatch in DH result');
        }
        
        if ($dhGen->serverNonce !== $resPq->serverNonce) {
            throw new \RuntimeException('Server nonce mismatch in DH result');
        }
        
        $authKey = new AuthKey(Helpers::getByteArray($gab));
        
        if ($dhGen instanceof DhGenOk) {
            $newNonceHash = $authKey->calcNewNonceHash($newNonce, 1);
            if ($dhGen->newNonceHash1 !== $newNonceHash) {
                throw new \RuntimeException('New nonce hash mismatch');
            }
            
            echo "==============================================\n";
            echo "✅ AUTHENTICATION COMPLETE!\n";
            echo "==============================================\n\n";
            echo "Successfully generated auth_key!\n";
            echo "Auth Key ID: " . bin2hex($authKey->getKeyId()) . "\n";
            echo "Time offset: {$timeOffset} seconds\n\n";
            
            return $authKey;
            
        } elseif ($dhGen instanceof DhGenRetry) {
            throw new \RuntimeException('DH generation retry required');
        } elseif ($dhGen instanceof DhGenFail) {
            throw new \RuntimeException('DH generation failed');
        } else {
            throw new \RuntimeException('Unknown DH generation result');
        }
    }
}
