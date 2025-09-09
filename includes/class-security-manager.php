<?php
/**
 * Security Manager for News Crawler Plugin
 * 
 * @package NewsCrawler
 * @subpackage Security
 * @since 2.0.0
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * セキュリティ管理クラス
 */
class NewsCrawlerSecurityManager {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * 暗号化キー
     */
    private $encryption_key;
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        // WordPress関数が利用可能になるまで待機
        if (function_exists('wp_salt')) {
            $this->encryption_key = $this->get_encryption_key();
        } else {
            // フォールバックキーを設定
            $this->encryption_key = $this->get_encryption_key();
        }
        $this->init_hooks();
    }
    
    /**
     * インスタンス取得
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * フック初期化
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'verify_admin_requests'));
        add_filter('news_crawler_sanitize_api_key', array($this, 'encrypt_api_key'));
        add_filter('news_crawler_get_api_key', array($this, 'decrypt_api_key'));
    }
    
    /**
     * 管理画面リクエストの検証
     */
    public function verify_admin_requests() {
        if (!is_admin() || !isset($_POST['action'])) {
            return;
        }
        
        // News Crawlerのアクションかチェック
        if (strpos($_POST['action'], 'news_crawler_') === 0) {
            $this->verify_nonce($_POST['action']);
        }
    }
    
    /**
     * nonce検証
     */
    public function verify_nonce($action) {
        $nonce_field = $action . '_nonce';

        // ライセンス管理用の汎用nonceを許容（admin-ajax 経由のAJAX）
        $alt_nonce = isset($_POST['nonce']) ? $_POST['nonce'] : ( $_POST['_ajax_nonce'] ?? null );
        if ($alt_nonce && wp_verify_nonce($alt_nonce, 'news_crawler_license_nonce')) {
            return;
        }

        // 開発用トグルは管理者であれば厳密なnonce不一致でも許容
        if ($action === 'news_crawler_toggle_dev_license' && current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST[$nonce_field])) {
            wp_die(__('Security check failed. Please try again.', 'news-crawler'));
        }
        
        if (!wp_verify_nonce($_POST[$nonce_field], $action)) {
            wp_die(__('Security check failed. Please try again.', 'news-crawler'));
        }
    }
    
    /**
     * APIキーの暗号化
     */
    public function encrypt_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        // OpenSSLが利用可能な場合
        if (function_exists('openssl_encrypt')) {
            return $this->openssl_encrypt($api_key);
        }
        
        // フォールバック: base64エンコード
        return base64_encode($api_key);
    }
    
    /**
     * APIキーの復号化
     */
    public function decrypt_api_key($encrypted_key) {
        if (empty($encrypted_key)) {
            return '';
        }
        
        // OpenSSLで暗号化されている場合
        if (strpos($encrypted_key, '::') !== false) {
            return $this->openssl_decrypt($encrypted_key);
        }
        
        // base64エンコードの場合
        $decoded = base64_decode($encrypted_key, true);
        return $decoded !== false ? $decoded : $encrypted_key;
    }
    
    /**
     * OpenSSL暗号化
     */
    private function openssl_encrypt($data) {
        $method = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt($data, $method, $this->encryption_key, 0, $iv);
        
        if ($encrypted === false) {
            return base64_encode($data); // フォールバック
        }
        
        return base64_encode($encrypted . '::' . $iv);
    }
    
    /**
     * OpenSSL復号化
     */
    private function openssl_decrypt($data) {
        $method = 'AES-256-CBC';
        $decoded = base64_decode($data);
        
        if ($decoded === false) {
            return '';
        }
        
        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) {
            return '';
        }
        
        list($encrypted_data, $iv) = $parts;
        
        $decrypted = openssl_decrypt($encrypted_data, $method, $this->encryption_key, 0, $iv);
        
        return $decrypted !== false ? $decrypted : '';
    }
    
    /**
     * 暗号化キーの取得
     */
    private function get_encryption_key() {
        // WordPress関数が利用可能かチェック
        if (function_exists('wp_salt')) {
            // WordPress固有のソルトを使用
            $key = wp_salt('secure_auth') . wp_salt('logged_in');
        } else {
            // フォールバック: サイト固有のキーを生成
            $key = defined('ABSPATH') ? ABSPATH : '';
            $key .= defined('DB_NAME') ? DB_NAME : '';
            $key .= defined('DB_USER') ? DB_USER : '';
            $key .= defined('DB_PASSWORD') ? DB_PASSWORD : '';
        }
        return hash('sha256', $key);
    }
    
    /**
     * 入力値のサニタイズ
     */
    public function sanitize_input($input, $type = 'text') {
        switch ($type) {
            case 'url':
                return esc_url_raw($input);
            case 'email':
                return sanitize_email($input);
            case 'textarea':
                return sanitize_textarea_field($input);
            case 'key':
                return sanitize_key($input);
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * APIキーのマスク表示
     */
    public function mask_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        $length = strlen($api_key);
        
        if ($length <= 8) {
            return str_repeat('*', $length);
        }
        
        return substr($api_key, 0, 4) . str_repeat('*', $length - 8) . substr($api_key, -4);
    }
    
    /**
     * CSRFトークンの生成
     */
    public function generate_csrf_token($action) {
        return wp_create_nonce($action);
    }
    
    /**
     * CSRFトークンの検証
     */
    public function verify_csrf_token($token, $action) {
        return wp_verify_nonce($token, $action);
    }
    
    /**
     * 管理者権限チェック
     */
    public function check_admin_capability($capability = 'manage_options') {
        if (!current_user_can($capability)) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'news-crawler'));
        }
    }
    
    /**
     * SQLインジェクション対策
     */
    public function prepare_sql($query, $args = array()) {
        global $wpdb;
        
        if (empty($args)) {
            return $query;
        }
        
        return $wpdb->prepare($query, $args);
    }
    
    /**
     * 既存のAPIキーを暗号化するマイグレーション
     */
    public function migrate_existing_api_keys() {
        // 基本設定のAPIキーをチェック
        $basic_settings = get_option('news_crawler_basic_settings', array());
        if (isset($basic_settings['openai_api_key']) && !empty($basic_settings['openai_api_key'])) {
            $api_key = $basic_settings['openai_api_key'];
            
            // 既に暗号化されているかチェック（::が含まれていない場合は平文）
            if (strpos($api_key, '::') === false && !empty($api_key)) {
                $encrypted_key = $this->encrypt_api_key($api_key);
                $basic_settings['openai_api_key'] = $encrypted_key;
                update_option('news_crawler_basic_settings', $basic_settings);
            }
        }
        
        // 代替設定のAPIキーをチェック
        $alt_settings = get_option('news_crawler_settings', array());
        if (isset($alt_settings['openai_api_key']) && !empty($alt_settings['openai_api_key'])) {
            $api_key = $alt_settings['openai_api_key'];
            
            // 既に暗号化されているかチェック（::が含まれていない場合は平文）
            if (strpos($api_key, '::') === false && !empty($api_key)) {
                $encrypted_key = $this->encrypt_api_key($api_key);
                $alt_settings['openai_api_key'] = $encrypted_key;
                update_option('news_crawler_settings', $alt_settings);
            }
        }
    }
}