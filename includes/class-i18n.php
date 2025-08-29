<?php
/**
 * Internationalization functionality for News Crawler Plugin
 * 
 * @package NewsCrawler
 * @subpackage I18n
 * @since 2.0.0
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 国際化管理クラス
 */
class NewsCrawlerI18n {
    
    /**
     * テキストドメイン
     */
    const TEXT_DOMAIN = 'news-crawler';
    
    /**
     * 初期化
     */
    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'load_textdomain'));
    }
    
    /**
     * テキストドメインの読み込み
     */
    public static function load_textdomain() {
        $plugin_dir = dirname(plugin_basename(dirname(__DIR__)));
        
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            $plugin_dir . '/languages'
        );
    }
    
    /**
     * 翻訳文字列の取得
     */
    public static function __($text) {
        return __($text, self::TEXT_DOMAIN);
    }
    
    /**
     * 翻訳文字列の出力
     */
    public static function _e($text) {
        _e($text, self::TEXT_DOMAIN);
    }
    
    /**
     * 複数形の翻訳
     */
    public static function _n($single, $plural, $number) {
        return _n($single, $plural, $number, self::TEXT_DOMAIN);
    }
    
    /**
     * コンテキスト付き翻訳
     */
    public static function _x($text, $context) {
        return _x($text, $context, self::TEXT_DOMAIN);
    }
}