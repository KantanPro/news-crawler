<?php
/**
 * セキュアなログ出力クラス
 * APIキーやトークンなどの機密情報を自動的にマスクしてログ出力する
 * 
 * @package NewsCrawler
 * @since 2.1.5
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawler_Secure_Logger {
    
    /**
     * ログファイルの最大サイズ（バイト）
     */
    const MAX_LOG_SIZE = 5 * 1024 * 1024; // 5MB
    
    /**
     * 保持するログファイルの数
     */
    const MAX_LOG_FILES = 5;
    
    /**
     * ログレベル定数
     */
    const LOG_LEVEL_ERROR = 1;
    const LOG_LEVEL_WARNING = 2;
    const LOG_LEVEL_INFO = 3;
    const LOG_LEVEL_DEBUG = 4;
    
    /**
     * 現在のログレベル
     */
    private static $log_level = self::LOG_LEVEL_INFO;
    
    /**
     * ログファイルのパス
     */
    private static $log_file = null;
    
    /**
     * ログ出力の制限（同じメッセージの連続出力を防ぐ）
     */
    private static $log_limits = [];
    
    /**
     * ログ制限の時間間隔（秒）
     */
    const LOG_LIMIT_INTERVAL = 60;
    
    /**
     * 機密情報のキー一覧
     */
    private static $sensitive_keys = [
        'openai_api_key',
        'youtube_api_key',
        'unsplash_access_key',
        'twitter_bearer_token',
        'twitter_api_key',
        'twitter_api_secret',
        'twitter_access_token',
        'twitter_access_token_secret',
        'license_key',
        'api_key',
        'access_token',
        'secret',
        'password',
        'token'
    ];
    
    /**
     * セキュアなログ出力
     * 
     * @param string $message ログメッセージ
     * @param mixed $data ログに含めるデータ（配列やオブジェクト）
     * @param string $prefix ログプレフィックス
     * @param int $level ログレベル
     * @param bool $limit_output ログ出力制限を適用するか
     */
    public static function log($message, $data = null, $prefix = 'NewsCrawler', $level = self::LOG_LEVEL_INFO, $limit_output = true) {
        // ログレベルチェック
        if ($level > self::$log_level) {
            return;
        }
        
        // ログ出力制限チェック
        if ($limit_output && self::is_log_limited($message, $prefix)) {
            return;
        }
        
        $log_message = $prefix . ': ' . $message;
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $masked_data = self::mask_sensitive_data($data);
                $log_message .= ' - ' . print_r($masked_data, true);
            } else {
                $log_message .= ' - ' . self::mask_sensitive_string($data);
            }
        }
        
        // ログファイルに書き込み
        self::write_to_log_file($log_message);
    }
    
    /**
     * エラーレベルのログ出力
     */
    public static function error($message, $data = null, $prefix = 'NewsCrawler') {
        self::log($message, $data, $prefix, self::LOG_LEVEL_ERROR);
    }
    
    /**
     * 警告レベルのログ出力
     */
    public static function warning($message, $data = null, $prefix = 'NewsCrawler') {
        self::log($message, $data, $prefix, self::LOG_LEVEL_WARNING);
    }
    
    /**
     * 情報レベルのログ出力
     */
    public static function info($message, $data = null, $prefix = 'NewsCrawler') {
        self::log($message, $data, $prefix, self::LOG_LEVEL_INFO);
    }
    
    /**
     * デバッグレベルのログ出力
     */
    public static function debug($message, $data = null, $prefix = 'NewsCrawler') {
        self::log($message, $data, $prefix, self::LOG_LEVEL_DEBUG);
    }
    
    /**
     * 配列やオブジェクト内の機密情報をマスク
     * 
     * @param mixed $data
     * @return mixed
     */
    public static function mask_sensitive_data($data) {
        if (is_array($data)) {
            $masked_data = array();
            foreach ($data as $key => $value) {
                if (self::is_sensitive_key($key)) {
                    $masked_data[$key] = '***masked***';
                } else {
                    $masked_data[$key] = self::mask_sensitive_data($value);
                }
            }
            return $masked_data;
        } elseif (is_object($data)) {
            $masked_data = clone $data;
            foreach (get_object_vars($masked_data) as $key => $value) {
                if (self::is_sensitive_key($key)) {
                    $masked_data->$key = '***masked***';
                } else {
                    $masked_data->$key = self::mask_sensitive_data($value);
                }
            }
            return $masked_data;
        } else {
            return self::mask_sensitive_string($data);
        }
    }
    
    /**
     * 文字列内の機密情報をマスク
     * 
     * @param string $string
     * @return string
     */
    public static function mask_sensitive_string($string) {
        if (!is_string($string)) {
            return $string;
        }
        
        // 長い文字列（APIキーやトークンの可能性）をマスク
        if (strlen($string) > 20) {
            return substr($string, 0, 4) . '***' . substr($string, -4);
        }
        
        return $string;
    }
    
    /**
     * キーが機密情報かどうか判定
     * 
     * @param string $key
     * @return bool
     */
    private static function is_sensitive_key($key) {
        $key_lower = strtolower($key);
        
        foreach (self::$sensitive_keys as $sensitive_key) {
            if (strpos($key_lower, $sensitive_key) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 設定情報を安全にログ出力
     * 
     * @param array $settings
     * @param string $prefix
     */
    public static function log_settings($settings, $prefix = 'NewsCrawler') {
        self::log('設定情報', $settings, $prefix);
    }
    
    /**
     * APIレスポンスを安全にログ出力
     * 
     * @param string $endpoint
     * @param int $response_code
     * @param string $response_body
     * @param string $prefix
     */
    public static function log_api_response($endpoint, $response_code, $response_body, $prefix = 'NewsCrawler') {
        $data = [
            'endpoint' => $endpoint,
            'response_code' => $response_code,
            'response_body' => $response_body
        ];
        
        self::log('APIレスポンス', $data, $prefix);
    }
    
    /**
     * ログファイルに書き込み
     * 
     * @param string $message
     */
    private static function write_to_log_file($message) {
        $log_file = self::get_log_file_path();
        
        // ログファイルのサイズチェック
        if (file_exists($log_file) && filesize($log_file) > self::MAX_LOG_SIZE) {
            self::rotate_log_files();
        }
        
        // ログファイルに書き込み
        $timestamp = '[' . date('Y-m-d H:i:s') . '] ';
        file_put_contents($log_file, $timestamp . $message . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * ログファイルのパスを取得
     * 
     * @return string
     */
    private static function get_log_file_path() {
        if (self::$log_file === null) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/news-crawler-logs';
            
            // ログディレクトリを作成
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            self::$log_file = $log_dir . '/news-crawler.log';
        }
        
        return self::$log_file;
    }
    
    /**
     * ログファイルをローテーション
     */
    private static function rotate_log_files() {
        $log_file = self::get_log_file_path();
        $log_dir = dirname($log_file);
        $base_name = basename($log_file, '.log');
        
        // 既存のログファイルを番号付きでリネーム
        for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
            $old_file = $log_dir . '/' . $base_name . '.' . $i . '.log';
            $new_file = $log_dir . '/' . $base_name . '.' . ($i + 1) . '.log';
            
            if (file_exists($old_file)) {
                if ($i === self::MAX_LOG_FILES - 1) {
                    // 最大ファイル数に達した場合は削除
                    unlink($old_file);
                } else {
                    rename($old_file, $new_file);
                }
            }
        }
        
        // 現在のログファイルを .1.log にリネーム
        if (file_exists($log_file)) {
            rename($log_file, $log_dir . '/' . $base_name . '.1.log');
        }
    }
    
    /**
     * ログレベルを設定
     * 
     * @param int $level
     */
    public static function set_log_level($level) {
        self::$log_level = $level;
    }
    
    /**
     * ログファイルをクリア
     */
    public static function clear_logs() {
        $log_file = self::get_log_file_path();
        $log_dir = dirname($log_file);
        $base_name = basename($log_file, '.log');
        
        // メインログファイルをクリア
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
        }
        
        // ローテーション済みファイルを削除
        for ($i = 1; $i <= self::MAX_LOG_FILES; $i++) {
            $rotated_file = $log_dir . '/' . $base_name . '.' . $i . '.log';
            if (file_exists($rotated_file)) {
                unlink($rotated_file);
            }
        }
    }
    
    /**
     * ログファイルのサイズを取得
     * 
     * @return array
     */
    public static function get_log_info() {
        $log_file = self::get_log_file_path();
        $log_dir = dirname($log_file);
        $base_name = basename($log_file, '.log');
        
        $info = [
            'main_log' => [
                'path' => $log_file,
                'size' => file_exists($log_file) ? filesize($log_file) : 0,
                'exists' => file_exists($log_file)
            ],
            'rotated_logs' => []
        ];
        
        // ローテーション済みファイルの情報
        for ($i = 1; $i <= self::MAX_LOG_FILES; $i++) {
            $rotated_file = $log_dir . '/' . $base_name . '.' . $i . '.log';
            if (file_exists($rotated_file)) {
                $info['rotated_logs'][] = [
                    'path' => $rotated_file,
                    'size' => filesize($rotated_file),
                    'number' => $i
                ];
            }
        }
        
        return $info;
    }
    
    /**
     * ログ出力が制限されているかチェック
     * 
     * @param string $message
     * @param string $prefix
     * @return bool
     */
    private static function is_log_limited($message, $prefix) {
        $key = $prefix . ':' . $message;
        $current_time = time();
        
        // 制限時間を過ぎたエントリをクリア
        foreach (self::$log_limits as $log_key => $timestamp) {
            if ($current_time - $timestamp > self::LOG_LIMIT_INTERVAL) {
                unset(self::$log_limits[$log_key]);
            }
        }
        
        // 同じメッセージが制限時間内に出力されているかチェック
        if (isset(self::$log_limits[$key])) {
            return true;
        }
        
        // 制限を記録
        self::$log_limits[$key] = $current_time;
        return false;
    }
    
    /**
     * ログ制限をクリア
     */
    public static function clear_log_limits() {
        self::$log_limits = [];
    }
    
    /**
     * ログ統計を取得
     * 
     * @return array
     */
    public static function get_log_stats() {
        $log_info = self::get_log_info();
        $total_size = $log_info['main_log']['size'];
        
        foreach ($log_info['rotated_logs'] as $rotated_log) {
            $total_size += $rotated_log['size'];
        }
        
        return [
            'total_size' => $total_size,
            'total_size_mb' => round($total_size / 1024 / 1024, 2),
            'main_log_size' => $log_info['main_log']['size'],
            'rotated_logs_count' => count($log_info['rotated_logs']),
            'log_level' => self::$log_level,
            'log_limits_count' => count(self::$log_limits)
        ];
    }
}
