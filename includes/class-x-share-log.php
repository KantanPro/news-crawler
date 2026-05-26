<?php
/**
 * X シェアログ
 *
 * @package News_Crawler
 */

if (!defined('ABSPATH')) {
    exit;
}

class News_Crawler_X_Share_Log {

    const OPTION_KEY = 'twitter_share_log';
    const MAX_ENTRIES = 50;

    /**
     * ログエントリを追加
     *
     * @param string $message メッセージ
     * @param string $level   レベル (success|error|info)
     * @param array  $context 追加情報
     * @param string $error   失敗原因
     */
    public static function add($message, $level = 'info', array $context = array(), $error = '') {
        $settings = get_option('news_crawler_basic_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }

        $entries = self::get_entries_from_settings($settings);
        array_unshift(
            $entries,
            array(
                'time' => current_time('mysql'),
                'level' => in_array($level, array('success', 'error', 'info'), true) ? $level : 'info',
                'message' => (string) $message,
                'error' => (string) $error,
                'context' => $context,
            )
        );

        $settings[self::OPTION_KEY] = array_slice($entries, 0, self::MAX_ENTRIES);
        update_option('news_crawler_basic_settings', $settings);
        wp_cache_delete('news_crawler_basic_settings', 'options');
    }

    /**
     * ログエントリ一覧を取得
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_entries() {
        $settings = get_option('news_crawler_basic_settings', array());
        if (!is_array($settings)) {
            return array();
        }

        return self::get_entries_from_settings($settings);
    }

    /**
     * ログをクリア
     */
    public static function clear() {
        $settings = get_option('news_crawler_basic_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }

        $settings[self::OPTION_KEY] = array();
        update_option('news_crawler_basic_settings', $settings);
        wp_cache_delete('news_crawler_basic_settings', 'options');
    }

    /**
     * レベル表示名
     *
     * @param string $level レベル
     * @return string
     */
    public static function get_level_label($level) {
        switch ($level) {
            case 'success':
                return '成功';
            case 'error':
                return '失敗';
            default:
                return '情報';
        }
    }

    /**
     * 設定配列からログを取得
     *
     * @param array $settings 設定
     * @return array<int, array<string, mixed>>
     */
    private static function get_entries_from_settings(array $settings) {
        $entries = $settings[self::OPTION_KEY] ?? array();
        return is_array($entries) ? $entries : array();
    }
}
