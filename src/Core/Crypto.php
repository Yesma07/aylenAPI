<?php

namespace App\Core;

use Exception;

class Crypto
{
    private const CIPHER = 'aes-256-cbc';
    private const KEY_ENV = 'APP_KEY';

    public static function encrypt(string $plaintext): string
    {
        $key = base64_decode($_ENV[self::KEY_ENV]);
        if(!$key || strlen($key) !== 32) {
            throw new Exception('La clave de encriptación no es válida.');
        }

        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = null;

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new Exception('Error al encriptar el texto: ' . openssl_error_string());
        }

        return base64_encode($iv. $tag . $ciphertext);
    }

    public static function decrypt(string $encrypted): string
    {
        $key = base64_decode($_ENV[self::KEY_ENV]);
        if(!$key || strlen($key) !== 32) {
            throw new Exception('La clave de encriptación no es válida.');
        }

        $data = base64_decode($encrypted, true);
        if ($data === false) {
            throw new Exception('Error al decodificar el texto encriptado.');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, 16);
        $ciphertext = substr($data, $ivLength + 16);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new Exception('Error al desencriptar el texto: ' . openssl_error_string());
        }

        return $plaintext;
    }
}