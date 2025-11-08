<?php
// includes/encrypt.php

// Đảm bảo bạn đã định nghĩa các hằng số này trong config.php của bạn
// ENCRYPTION_KEY: Một chuỗi 32 byte ngẫu nhiên
// ENCRYPTION_IV: Một chuỗi 16 byte ngẫu nhiên

function encrypt_data($data) {
    if (!defined('ENCRYPTION_KEY') || !defined('ENCRYPTION_IV')) {
        // Or handle this error more gracefully
        throw new Exception("Encryption constants are not defined.");
    }
    $json_data = json_encode($data);
    $encrypted = openssl_encrypt($json_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    return base64_encode($encrypted);
}

function decrypt_data($encoded_data) {
    if (!defined('ENCRYPTION_KEY') || !defined('ENCRYPTION_IV')) {
        throw new Exception("Encryption constants are not defined.");
    }
    $decoded_data = base64_decode($encoded_data);
    $decrypted = openssl_decrypt($decoded_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, ENCRYPTION_IV);
    return json_decode($decrypted, true);
}
