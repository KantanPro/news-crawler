<?php
/**
 * X 設定用の機密情報暗号化
 *
 * @package News_Crawler
 */

if (!defined('ABSPATH')) {
    exit;
}

class News_Crawler_X_Crypto {

    /**
     * 平文を暗号化する
     *
     * @param string $value 平文
     * @return string
     */
    public static function encrypt($value) {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            return base64_encode($value);
        }

        $key = hash('sha256', wp_salt('auth'), true);
        $iv = openssl_random_pseudo_bytes(16);
        if ($iv === false) {
            return base64_encode($value);
        }

        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return base64_encode($value);
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * 暗号文を復号する
     *
     * @param string $value 暗号文
     * @return string
     */
    public static function decrypt($value) {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return $value;
        }

        if (!function_exists('openssl_decrypt') || strlen($decoded) <= 16) {
            return $decoded !== false ? $decoded : $value;
        }

        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);
        $key = hash('sha256', wp_salt('auth'), true);
        $plain = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $plain !== false ? $plain : $value;
    }
}
