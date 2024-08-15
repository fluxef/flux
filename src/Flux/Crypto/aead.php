<?php
declare(strict_types=1);

namespace Flux\Crypto;

use Exception;
use SodiumException;
use const SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13;
use const SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;
use const SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
use const SODIUM_CRYPTO_PWHASH_SALTBYTES;
use const SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

class aead
{
    /**
     * @throws SodiumException
     * @throws Exception
     */
    public static function ArrayEncryptBase64(array $data, string $key): string
    {
        if (empty($key)) {
            throw new Exception("key is empty");
        }

        $msg = json_encode($data);

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($msg, $nonce, $key));

    }

    /**
     * @throws Exception
     */
    public static function ArrayDecryptBase64(string $Base64message, string $key): array
    {
        if (empty($key)) {
            throw new Exception("empty key");
        }

        $msg = base64_decode($Base64message, true);
        if ($msg === false) {
            throw new Exception("base64 data is invalid");
        }

        if (strlen($msg) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new Exception("msg is too short");
        }

        $nonce = substr($msg, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($msg, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);


        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ($plaintext === false) {
            throw new Exception("msg is invalid");
        }

        return json_decode($plaintext, true);

    }


    public static function createKey(string $passphrase, string $salt = ''): string
    {

        // IMPORTANT: setting a fixed salt is really a bad idea for generated password hashes that are somehow
        // stored or transmitted. But if this is only for local use of the generated key for sodium_crypto_secretbox
        // is it somehow acceptable
        if (empty($salt))
            $salt = str_repeat("\x80", SODIUM_CRYPTO_PWHASH_SALTBYTES);

        return sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $passphrase,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);

    }
}
