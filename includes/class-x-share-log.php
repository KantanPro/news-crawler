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

    const OPTION_KEY = 'news_crawler_x_share_log';
    const LEGACY_OPTION_KEY = 'twitter_share_log';
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
        $entries = self::get_entries();
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

        update_option(self::OPTION_KEY, array_slice($entries, 0, self::MAX_ENTRIES), false);
        wp_cache_delete(self::OPTION_KEY, 'options');
    }

    /**
     * ログエントリ一覧を取得
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_entries() {
        $entries = get_option(self::OPTION_KEY, null);
        if (is_array($entries) && !empty($entries)) {
            return $entries;
        }

        return self::migrate_legacy_entries();
    }

    /**
     * ログをクリア
     */
    public static function clear() {
        update_option(self::OPTION_KEY, array(), false);
        wp_cache_delete(self::OPTION_KEY, 'options');
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
     * 旧 basic_settings 内のログを専用オプションへ移行
     *
     * @return array<int, array<string, mixed>>
     */
    private static function migrate_legacy_entries() {
        $basic = get_option('news_crawler_basic_settings', array());
        if (!is_array($basic) || empty($basic[self::LEGACY_OPTION_KEY]) || !is_array($basic[self::LEGACY_OPTION_KEY])) {
            return array();
        }

        $entries = array_values($basic[self::LEGACY_OPTION_KEY]);
        update_option(self::OPTION_KEY, $entries, false);
        wp_cache_delete(self::OPTION_KEY, 'options');

        unset($basic[self::LEGACY_OPTION_KEY]);
        update_option('news_crawler_basic_settings', $basic);
        wp_cache_delete('news_crawler_basic_settings', 'options');

        return $entries;
    }
}
