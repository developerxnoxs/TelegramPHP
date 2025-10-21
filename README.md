# TelethonPHP - Telegram MTProto Library for PHP

⚠️ **STATUS: DEVELOPMENT / NOT PRODUCTION READY** ⚠️

Library PHP untuk Telegram MTProto API yang terinspirasi dari arsitektur **Telethon** (Python).

## ⚠️ PENTING - Baca Ini Dulu!

**Library ini adalah FONDASI untuk implementasi MTProto yang lengkap.** Saat ini:

- ✅ **Crypto layer sudah production-ready** (AES-IGE, RSA, AuthKey)
- ✅ **Session management sudah berfungsi** 
- ✅ **TL serialization dasar sudah ada**
- ❌ **Authentication belum lengkap** (DH key exchange perlu diselesaikan)
- ❌ **API methods belum diimplementasi** (messages, users, dll)
- ❌ **Update handling belum ada**

**Untuk production sekarang**, gunakan:
- [MadelineProto](https://github.com/danog/MadelineProto) - Library PHP MTProto yang sudah mature

**Library ini cocok untuk**:
- Learning MTProto protocol
- Research dan eksperimen
- Development jangka panjang
- Reference implementation based on Telethon

Lihat [PRODUCTION_STATUS.md](PRODUCTION_STATUS.md) untuk detail lengkap.

## Struktur Library (Berdasarkan Telethon)

```
src/
├── Client/                 # Client utama dan Auth API
│   ├── TelegramClient.php
│   └── Auth.php
├── Crypto/                 # ✅ PRODUCTION READY
│   ├── AES.php            # AES-IGE encryption (sama seperti Telethon)
│   ├── RSA.php            # RSA dengan Telegram public keys
│   └── AuthKey.php        # Authorization key management
├── TL/                     # Type Language
│   ├── TLObject.php       # Base class
│   ├── BinaryReader.php   # ✅ Binary deserialization
│   ├── BinaryWriter.php   # ✅ Binary serialization
│   ├── Types/             # TL Types (perlu dilengkapi)
│   │   ├── ResPQ.php
│   │   └── PQInnerData.php
│   └── Functions/         # TL Functions (perlu dilengkapi)
│       └── ReqPqMultiRequest.php
├── Network/                # Network layer
│   ├── Connection.php      # ✅ TCP connection
│   ├── MTProtoPlainSender.php  # Unencrypted sender
│   └── Authenticator.php   # ⚠️  DH key exchange (partial)
├── Sessions/               # ✅ PRODUCTION READY
│   ├── AbstractSession.php
│   ├── MemorySession.php
│   └── FileSession.php
└── Helpers/                # ✅ Helper functions
    └── Helpers.php         # Factorization, nonce gen, dll

```

## Yang Sudah Diimplementasi dengan Benar

### 1. Cryptography (Production Ready)

```php
use TelethonPHP\Crypto\AES;
use TelethonPHP\Crypto\RSA;
use TelethonPHP\Crypto\AuthKey;

// AES-IGE Encryption (sama seperti di Telethon)
$key = random_bytes(32);
$iv = random_bytes(32);
$encrypted = AES::encryptIGE($plaintext, $key, $iv);
$decrypted = AES::decryptIGE($encrypted, $key, $iv);

// RSA dengan Telegram public keys
RSA::initDefaultKeys();
$encrypted = RSA::encrypt($fingerprint, $data);

// AuthKey management
$authKey = new AuthKey($keyData);
$keyId = $authKey->getKeyId();
$hash = $authKey->calcNewNonceHash($newNonce, 1);
```

### 2. Binary Serialization

```php
use TelethonPHP\TL\BinaryWriter;
use TelethonPHP\TL\BinaryReader;

// Writing
$writer = new BinaryWriter();
$writer->writeInt(12345);
$writer->writeLong(9876543210);
$writer->writeString("Hello");
$data = $writer->getValue();

// Reading
$reader = new BinaryReader($data);
$int = $reader->readInt();
$long = $reader->readLong();
$string = $reader->readString();
```

### 3. Session Management

```php
use TelethonPHP\Sessions\FileSession;
use TelethonPHP\Sessions\MemorySession;

// File session (persistent)
$session = new FileSession('my_session.json');
$session->setDC(2, '149.154.167.51', 443);
$session->setAuthKey($authKeyData);
$session->save();

// Memory session (temporary)
$session = new MemorySession();
```

### 4. Helper Functions

```php
use TelethonPHP\Helpers\Helpers;

// Factorization (Pollard's rho-Brent, sama seperti Telethon)
[$p, $q] = Helpers::factorize(1837939793969);

// Nonce generation
$randomLong = Helpers::generateRandomLong();
$messageId = Helpers::generateMessageId();

// Key derivation untuk DH
[$key, $iv] = Helpers::generateKeyDataFromNonce($serverNonce, $newNonce);
```

## Yang Masih Perlu Diimplementasi

Lihat [PRODUCTION_STATUS.md](PRODUCTION_STATUS.md) untuk detail, tapi summary:

1. **MTProto Authentication** - DH key exchange lengkap (50% done)
2. **TL Schema** - Generate 500+ types dan functions dari .tl files  
3. **MTProtoSender** - Encrypted message handling
4. **API Methods** - messages.sendMessage, users.getFullUser, dll
5. **Update Handling** - Long polling dan event system
6. **File Operations** - Upload/download dengan chunking
7. **Multi-DC** - DC migration dan routing

**Estimasi untuk production-ready**: 10-16 minggu development penuh.

## Installation

```bash
composer install
```

## Demo & Testing

```bash
# Demo crypto dan components
php demo.php

# Login test (simulated)
php test_login_auto.php

# Interactive login (simulated)
php test_login.php
```

## Dokumentasi Referensi

### Implementasi yang Diikuti
1. **Telethon Source Code** - `.pythonlibs/lib/python3.11/site-packages/telethon/`
   - Crypto implementation
   - Network protocol
   - TL serialization
   - Authentication flow

2. **Telegram Documentation**
   - https://core.telegram.org/mtproto
   - https://core.telegram.org/mtproto/auth_key
   - https://core.telegram.org/schema

### Comparison dengan Libraries Lain

| Feature | TelethonPHP | MadelineProto | Telethon |
|---------|------------|---------------|----------|
| Language | PHP | PHP | Python |
| Arsitektur | Telethon-inspired | Custom | Original |
| Status | Development | Production | Production |
| MTProto | Partial | Full | Full |
| API Coverage | 0% | 100% | 100% |
| Best For | Learning | Production | Production |

## Development Roadmap

### Immediate Next Steps (Untuk Lanjut Development)

1. **Complete Authenticator.php**
   ```
   Referensi: .pythonlibs/.../telethon/network/authenticator.py
   - Implement semua TL types untuk auth
   - Complete DH exchange
   - Test dengan Telegram server
   ```

2. **Implement TL Schema Parser**
   ```
   - Download schema.tl dari Telegram
   - Parse dan generate PHP classes
   - Automate type/function generation
   ```

3. **Implement MTProtoSender**
   ```
   Referensi: .pythonlibs/.../telethon/network/mtprotosender.py
   - Message encryption
   - Seq number handling
   - Container dan gzip
   ```

4. **Basic API Methods**
   ```
   - auth.sendCode
   - auth.signIn
   - messages.sendMessage
   ```

## Contributing

Jika ingin melanjutkan development:

1. Fork repository
2. Study Telethon implementation
3. Implement one component at a time
4. Test dengan Telegram test servers
5. Submit PR

## Architecture Notes

Library ini mengikuti arsitektur Telethon:

```
TelegramClient
    ↓
MTProtoSender (encrypted)
    ↓
Connection (TCP)
```

Dengan layer:
- **Crypto Layer**: AES, RSA, AuthKey
- **TL Layer**: Serialization/deserialization
- **Network Layer**: Connection, MTProtoSender
- **Session Layer**: State persistence
- **Client Layer**: High-level API

## Requirements

- PHP >= 8.2
- Extensions:
  - ext-openssl (kriptografi)
  - ext-mbstring (string handling)
  - ext-curl (HTTP transport)
  - ext-json (serialization)
  - ext-gmp (big integer)

## License

MIT License

## Disclaimer

Ini adalah project educational/research. Untuk production, gunakan MadelineProto yang sudah mature dan battle-tested.

## Credits

- Inspired by [Telethon](https://github.com/LonamiWebs/Telethon) by Lonami
- MTProto protocol by Telegram
- Factorization algorithm from Pollard's rho-Brent

---

**⚠️  Reminder**: Ini adalah fondasi yang solid, tapi masih jauh dari production-ready. Lihat PRODUCTION_STATUS.md untuk detail lengkap tentang apa yang sudah dan belum diimplementasi.
