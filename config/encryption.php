<?php

require_once __DIR__ . '/app.php';

function encryptData(string $data): string {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, $key, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

function decryptData(string $encryptedData): string {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $parts = explode('::', base64_decode($encryptedData), 2);
    if (count($parts) !== 2) return '';
    [$iv, $encrypted] = $parts;
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, 0, $iv);
}
